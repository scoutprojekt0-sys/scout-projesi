<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\PlayerCareerTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PlayerCareerController extends Controller
{
    public function timeline(int $playerId): JsonResponse
    {
        $timeline = PlayerCareerTimeline::where('player_id', $playerId)
            ->with('club:id,name')
            ->where('verification_status', 'verified')
            ->orderBy('start_date', 'desc')
            ->get();

        $current = $timeline->where('is_current', true)->first();
        $history = $timeline->where('is_current', false);

        return response()->json([
            'ok' => true,
            'data' => [
                'current' => $current,
                'history' => $history->values(),
                'total_clubs' => $timeline->unique('club_id')->count(),
                'career_goals' => $timeline->sum('goals'),
                'career_appearances' => $timeline->sum('appearances'),
                'career_assists' => $timeline->sum('assists'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureCareerStoreAccess($request->user())) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'player_id' => ['required', Rule::exists('users', 'id')->where('role', 'player')],
            'club_id' => ['required', Rule::exists('users', 'id')->where('role', 'team')],
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'season_start' => 'required|string|max:10',
            'season_end' => 'nullable|string|max:10',
            'is_current' => 'nullable|boolean',
            'position' => 'nullable|string|max:50',
            'contract_type' => 'required|in:professional,youth,amateur,loan',
            'appearances' => 'nullable|integer|min:0',
            'goals' => 'nullable|integer|min:0',
            'assists' => 'nullable|integer|min:0',
            'minutes_played' => 'nullable|integer|min:0',
            'yellow_cards' => 'nullable|integer|min:0',
            'red_cards' => 'nullable|integer|min:0',
            'source_url' => 'required|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // If setting as current, unset other current entries for this player
        if ($request->is_current) {
            PlayerCareerTimeline::where('player_id', $request->player_id)
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }

        $career = PlayerCareerTimeline::create(array_merge(
            $validator->validated(),
            [
                'created_by' => auth()->id(),
                'verification_status' => 'pending',
                'confidence_score' => 0.7,
            ]
        ));

        // Add to moderation queue
        ModerationQueue::create([
            'model_type' => 'PlayerCareerTimeline',
            'model_id' => $career->id,
            'status' => 'pending',
            'priority' => 'medium',
            'reason' => 'new_entry',
            'proposed_changes' => $career->toArray(),
            'source_url' => $request->source_url,
            'confidence_score' => 0.7,
            'submitted_by' => auth()->id(),
        ]);

        DataAuditLog::logChange(
            'PlayerCareerTimeline',
            $career->id,
            'created',
            null,
            $career->toArray(),
            auth()->id(),
            'New career timeline entry'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Career entry created successfully. Awaiting verification.',
            'data' => $career->load('club'),
        ], 201);
    }

    public function statistics(int $playerId): JsonResponse
    {
        $timeline = PlayerCareerTimeline::where('player_id', $playerId)
            ->where('verification_status', 'verified')
            ->get();

        $player = \App\Models\User::query()->find($playerId);
        $rating = (float) ($player?->rating ?? 0);

        $byClub = $timeline->groupBy('club_id')->map(function ($entries) {
            return [
                'club_id' => $entries->first()->club_id,
                'club_name' => $entries->first()->club->name ?? 'Unknown',
                'total_appearances' => $entries->sum('appearances'),
                'total_goals' => $entries->sum('goals'),
                'total_assists' => $entries->sum('assists'),
                'total_minutes' => $entries->sum('minutes_played'),
                'seasons' => $entries->count(),
            ];
        })->values();

        $bySeason = $timeline->groupBy('season_start')->map(function ($entries, $season) {
            return [
                'season' => $season,
                'appearances' => $entries->sum('appearances'),
                'goals' => $entries->sum('goals'),
                'assists' => $entries->sum('assists'),
                'minutes_played' => $entries->sum('minutes_played'),
            ];
        });

        $careerTotals = [
            'appearances' => $timeline->sum('appearances'),
            'goals' => $timeline->sum('goals'),
            'assists' => $timeline->sum('assists'),
            'minutes_played' => $timeline->sum('minutes_played'),
            'yellow_cards' => $timeline->sum('yellow_cards'),
            'red_cards' => $timeline->sum('red_cards'),
        ];
        $achievementItems = $this->buildAchievementItems(
            $careerTotals,
            $timeline,
            $rating
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'by_club' => $byClub,
                'by_season' => $bySeason,
                'career_totals' => $careerTotals,
                'achievement_items' => $achievementItems,
            ],
        ]);
    }

    public function activity(Request $request, int $playerId): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer) {
            return response()->json([
                'ok' => false,
                'message' => 'Yetkisiz istek.',
            ], 401);
        }

        $isAdmin = in_array($viewer->role, ['admin', 'super_admin'], true)
            || strtolower((string) ($viewer->editor_role ?? '')) === 'admin';

        if ((int) $viewer->id !== $playerId && ! $isAdmin) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu aktivite akisina erisimin yok.',
            ], 403);
        }

        $limit = max(1, min((int) $request->query('limit', 10), 20));
        $items = collect();

        $latestView = DB::table('profile_views as pv')
            ->leftJoin('users as viewer', 'viewer.id', '=', 'pv.viewer_user_id')
            ->where('pv.viewed_user_id', $playerId)
            ->orderByDesc('pv.viewed_at')
            ->select([
                'pv.viewed_at',
                'viewer.name as viewer_name',
                'viewer.role as viewer_role',
            ])
            ->first();

        if ($latestView) {
            $viewCount = (int) DB::table('profile_views')
                ->where('viewed_user_id', $playerId)
                ->count();

            $items->push([
                'type' => 'profile_view',
                'title' => 'Scout seni izlemeye aldi',
                'subtitle' => $latestView->viewer_name
                    ? sprintf('%s profilini inceledi. Toplam %d goruntulenme var.', $latestView->viewer_name, $viewCount)
                    : sprintf('Profilin toplam %d kez goruntulendi.', $viewCount),
                'occurred_at' => $latestView->viewed_at,
            ]);
        }

        $latestReview = DB::table('profile_reviews')
            ->where('target_id', $playerId)
            ->whereIn('status', ['published', 'reported'])
            ->orderByDesc('created_at')
            ->select(['body', 'author_role', 'created_at'])
            ->first();

        if ($latestReview) {
            $items->push([
                'type' => 'review',
                'title' => 'Yorum eklendi',
                'subtitle' => trim((string) $latestReview->body) !== ''
                    ? mb_strimwidth((string) $latestReview->body, 0, 110, '...')
                    : (($latestReview->author_role ?: 'Uye').' tarafindan yeni yorum eklendi.'),
                'occurred_at' => $latestReview->created_at,
            ]);
        }

        $latestInbox = DB::table('contacts')
            ->join('users as sender', 'sender.id', '=', 'contacts.from_user_id')
            ->where('contacts.to_user_id', $playerId)
            ->orderByDesc('contacts.created_at')
            ->select([
                'contacts.subject',
                'contacts.message',
                'contacts.created_at',
                'sender.name as sender_name',
            ])
            ->first();

        if ($latestInbox) {
            $subject = trim((string) ($latestInbox->subject ?? ''));
            $fallback = trim((string) ($latestInbox->message ?? ''));
            $items->push([
                'type' => 'message',
                'title' => 'Yeni mesaj aldin',
                'subtitle' => $subject !== ''
                    ? $subject
                    : ($fallback !== ''
                        ? mb_strimwidth($fallback, 0, 110, '...')
                        : sprintf('%s sana yeni bir mesaj gonderdi.', $latestInbox->sender_name ?? 'Bir uye')),
                'occurred_at' => $latestInbox->created_at,
            ]);
        }

        $latestApplication = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->where('applications.player_user_id', $playerId)
            ->orderByDesc('applications.created_at')
            ->select([
                'applications.status',
                'applications.created_at',
                'opportunities.title as opportunity_title',
            ])
            ->first();

        if ($latestApplication) {
            $items->push([
                'type' => 'application',
                'title' => 'Yeni davet veya teklif geldi',
                'subtitle' => trim((string) ($latestApplication->opportunity_title ?? '')) !== ''
                    ? (string) $latestApplication->opportunity_title
                    : ('Basvuru durumun '.$latestApplication->status.' olarak guncellendi.'),
                'occurred_at' => $latestApplication->created_at,
            ]);
        }

        $latestNotification = DB::table('notifications')
            ->where('user_id', $playerId)
            ->orderByDesc('created_at')
            ->select(['title', 'message', 'created_at'])
            ->first();

        if ($latestNotification) {
            $items->push([
                'type' => 'notification',
                'title' => trim((string) ($latestNotification->title ?? '')) !== ''
                    ? (string) $latestNotification->title
                    : 'Profil etkilesimi artti',
                'subtitle' => trim((string) ($latestNotification->message ?? '')) !== ''
                    ? (string) $latestNotification->message
                    : 'Yeni bir bildirim kaydi olustu.',
                'occurred_at' => $latestNotification->created_at,
            ]);
        }

        $feed = $items
            ->filter(fn ($item) => ! empty($item['occurred_at']))
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'data' => $feed,
        ]);
    }

    private function buildAchievementItems(array $totals, $timeline, float $rating): array
    {
        $items = [];
        $currentClub = optional($timeline->where('is_current', true)->first()?->club)->name ?? '-';

        if (($totals['goals'] ?? 0) > 0 || ($totals['assists'] ?? 0) > 0) {
            $items[] = [
                'category' => 'Bireysel',
                'icon' => 'BG',
                'title' => 'Gol Katkisi Lideri',
                'description' => sprintf(
                    'Toplam %d gol ve %d asist ile hucum katkinda one ciktin.',
                    (int) ($totals['goals'] ?? 0),
                    (int) ($totals['assists'] ?? 0)
                ),
                'meta' => 'Son donem performansi',
            ];
        }

        if (($totals['appearances'] ?? 0) >= 20) {
            $items[] = [
                'category' => 'Takim',
                'icon' => 'FI',
                'title' => 'Form Istikrari',
                'description' => sprintf(
                    '%d resmi macla duzenli forma giyerek takim ritmini korudun.',
                    (int) ($totals['appearances'] ?? 0)
                ),
                'meta' => 'Sezon geneli',
            ];
        }

        if ($rating >= 8.0) {
            $items[] = [
                'category' => 'Bireysel',
                'icon' => 'YP',
                'title' => 'Yuksek Performans Seviyesi',
                'description' => 'Genel oyuncu puanin '.number_format($rating, 1).' seviyesinde.',
                'meta' => 'Guncel oyuncu puani',
            ];
        }

        $clubCount = $timeline->pluck('club_id')->filter()->unique()->count();
        if ($clubCount > 0) {
            $items[] = [
                'category' => 'Kariyer',
                'icon' => 'KY',
                'title' => 'Kariyer Yolculugu',
                'description' => sprintf(
                    '%s dahil %d farkli kulup deneyimiyle kariyer cizgini genislettin.',
                    $currentClub,
                    $clubCount
                ),
                'meta' => sprintf(
                    '%d mac, %d gol',
                    (int) ($totals['appearances'] ?? 0),
                    (int) ($totals['goals'] ?? 0)
                ),
            ];
        }

        return $items;
    }

    private function ensureCareerStoreAccess($user): ?JsonResponse
    {
        $role = strtolower((string) ($user?->role ?? ''));
        $allowedRoles = ['admin', 'super_admin', 'team', 'club', 'kulup', 'manager', 'menajer', 'scout', 'coach'];

        if ($user && in_array($role, $allowedRoles, true)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Kariyer gecmisi kaydi olusturma yetkiniz yok.',
        ], 403);
    }
}
