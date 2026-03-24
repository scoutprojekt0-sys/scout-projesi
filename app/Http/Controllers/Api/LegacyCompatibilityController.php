<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lawyer;
use App\Models\SuccessStory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LegacyCompatibilityController extends Controller
{
    public function discoveryCoachNeeds(): JsonResponse
    {
        $rows = DB::table('opportunities as o')
            ->join('users as u', 'u.id', '=', 'o.team_user_id')
            ->where('o.status', 'open')
            ->where('u.role', 'coach')
            ->select([
                'o.id',
                'o.position',
                'o.city',
                'o.age_min',
                'o.age_max',
                DB::raw('o.details as description'),
                DB::raw('u.name as author_name'),
                DB::raw('u.name as manager_name'),
                DB::raw('u.name as club_name'),
                'o.created_at',
            ])
            ->orderByDesc('o.created_at')
            ->limit(40)
            ->get();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function discoveryBoosts(): JsonResponse
    {
        $rows = DB::table('player_boosts as pb')
            ->join('users as u', 'u.id', '=', 'pb.user_id')
            ->join('boost_packages as bp', 'bp.id', '=', 'pb.boost_package_id')
            ->where('u.role', 'player')
            ->where('pb.status', 'active')
            ->where(function ($query) {
                $query->whereNull('pb.ends_at')
                    ->orWhere('pb.ends_at', '>=', now());
            })
            ->orderByDesc('bp.discover_score')
            ->orderByDesc('pb.activated_at')
            ->orderByDesc('pb.created_at')
            ->limit(120)
            ->get([
                'u.id',
                'u.name',
                'u.position',
                'u.city',
                DB::raw("COALESCE(NULLIF(u.source_url, ''), 'Sponsorlu oyuncu profili') as summary"),
                DB::raw('bp.name as package_label'),
                DB::raw('pb.ends_at as expires_at'),
                DB::raw('pb.activated_at as created_at'),
                DB::raw('pb.updated_at as updated_at'),
                DB::raw('pb.user_id as boosted_user_id'),
                DB::raw('bp.discover_score as discover_score'),
            ])
            ->unique('boosted_user_id')
            ->values()
            ->take(40)
            ->map(function ($row) {
                unset($row->boosted_user_id, $row->discover_score);
                return $row;
            });

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function publicPlayersQualitySummary(): JsonResponse
    {
        $base = DB::table('users')->where('role', 'player');
        $total = (clone $base)->count();
        $high = (clone $base)->where('confidence_score', '>=', 80)->count();
        $medium = (clone $base)->whereBetween('confidence_score', [50, 79.99])->count();
        $low = max(0, $total - $high - $medium);

        return response()->json([
            'ok' => true,
            'data' => [
                'total' => $total,
                'high' => $high,
                'medium' => $medium,
                'low' => $low,
            ],
        ]);
    }

    public function communityEventsIndex(Request $request): JsonResponse
    {
        $trialOnly = (string) $request->query('trial_only', '') === '1';
        $rows = DB::table('opportunities as o')
            ->join('users as u', 'u.id', '=', 'o.team_user_id')
            ->where('o.status', 'open')
            ->select([
                'o.id',
                'o.title',
                'o.city',
                'o.created_at',
                DB::raw('o.details as venue'),
                DB::raw("CASE WHEN u.role = 'coach' THEN 'training_session' ELSE 'trial_day' END as event_type"),
                DB::raw("'open' as status"),
                DB::raw('u.name as organizer_name'),
                DB::raw('u.role as organizer_role'),
                DB::raw('u.name as organizer_club_name'),
            ])
            ->orderByDesc('o.created_at')
            ->limit(120)
            ->get()
            ->map(function ($row) use ($trialOnly) {
                $eventType = (string) $row->event_type;
                if ($trialOnly && !in_array($eventType, ['trial_day', 'trial', 'trial_match'], true)) {
                    return null;
                }
                return [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'event_type' => $eventType,
                    'event_date' => $row->created_at,
                    'city' => (string) ($row->city ?? ''),
                    'venue' => (string) ($row->venue ?? ''),
                    'status' => (string) $row->status,
                    'organizer' => [
                        'name' => (string) ($row->organizer_name ?? ''),
                        'role' => (string) ($row->organizer_role ?? ''),
                        'club_name' => (string) ($row->organizer_club_name ?? ''),
                    ],
                ];
            })
            ->filter()
            ->values();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function communityEventsShow(int $id): JsonResponse
    {
        $row = DB::table('opportunities as o')
            ->join('users as u', 'u.id', '=', 'o.team_user_id')
            ->where('o.id', $id)
            ->select([
                'o.id',
                'o.title',
                'o.city',
                'o.created_at',
                DB::raw('o.details as venue'),
                DB::raw("CASE WHEN u.role = 'coach' THEN 'training_session' ELSE 'trial_day' END as event_type"),
                DB::raw("'open' as status"),
                DB::raw('u.name as organizer_name'),
                DB::raw('u.role as organizer_role'),
                DB::raw('u.name as organizer_club_name'),
            ])
            ->first();

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Etkinlik bulunamadi.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'event_type' => (string) $row->event_type,
                'event_date' => $row->created_at,
                'city' => (string) ($row->city ?? ''),
                'venue' => (string) ($row->venue ?? ''),
                'status' => (string) $row->status,
                'organizer' => [
                    'name' => (string) ($row->organizer_name ?? ''),
                    'role' => (string) ($row->organizer_role ?? ''),
                    'club_name' => (string) ($row->organizer_club_name ?? ''),
                ],
            ],
        ]);
    }

    public function communityEventsRegister(Request $request, int $id): JsonResponse
    {
        if (!Schema::hasTable('applications')) {
            return response()->json(['ok' => true, 'message' => 'Basvuru kaydi alindi.']);
        }

        $userId = (int) $request->user()->id;
        $exists = DB::table('applications')
            ->where('opportunity_id', $id)
            ->where('player_user_id', $userId)
            ->exists();
        if ($exists) {
            return response()->json(['ok' => true, 'message' => 'Basvuru zaten mevcut.']);
        }

        DB::table('applications')->insert([
            'opportunity_id' => $id,
            'player_user_id' => $userId,
            'message' => 'Community event basvurusu',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Basvuru gonderildi.']);
    }

    public function successStoriesIndex(): JsonResponse
    {
        if (Schema::hasTable('success_stories')) {
            $rows = SuccessStory::query()
                ->where('status', 'approved')
                ->orderByDesc('approved_at')
                ->latest('created_at')
                ->latest('id')
                ->limit(100)
                ->get([
                    'id',
                    'full_name',
                    'sport',
                    'story_text',
                    'old_club',
                    'new_club',
                    'image_url',
                    'status',
                    'created_at',
                ])
                ->map(fn (SuccessStory $story) => [
                    'id' => (int) $story->id,
                    'full_name' => (string) $story->full_name,
                    'sport' => (string) $story->sport,
                    'story_text' => (string) $story->story_text,
                    'old_club' => $story->old_club,
                    'new_club' => $story->new_club,
                    'image_url' => $story->image_url,
                    'status' => (string) $story->status,
                    'created_at' => optional($story->created_at)?->toIso8601String(),
                ])
                ->values();

            return response()->json(['ok' => true, 'data' => $rows]);
        }

        $rows = Cache::get('legacy_success_stories', []);
        if (! is_array($rows)) {
            $rows = [];
        }

        return response()->json(['ok' => true, 'data' => array_values($rows)]);
    }

    public function adminSuccessStoriesIndex(Request $request): JsonResponse
    {
        if (! Schema::hasTable('success_stories')) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $status = strtolower(trim((string) $request->query('status', '')));
        $query = trim((string) $request->query('q', ''));
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $stories = SuccessStory::query()
            ->with(['user:id,name,email,role', 'approver:id,name,email'])
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner
                        ->where('full_name', 'like', '%'.$query.'%')
                        ->orWhere('sport', 'like', '%'.$query.'%')
                        ->orWhere('story_text', 'like', '%'.$query.'%')
                        ->orWhereHas('user', function ($userQuery) use ($query) {
                            $userQuery
                                ->where('name', 'like', '%'.$query.'%')
                                ->orWhere('email', 'like', '%'.$query.'%');
                        });
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        $stories->getCollection()->transform(function (SuccessStory $story) {
            return [
                'id' => (int) $story->id,
                'full_name' => (string) $story->full_name,
                'sport' => (string) $story->sport,
                'story_text' => (string) $story->story_text,
                'old_club' => $story->old_club,
                'new_club' => $story->new_club,
                'image_url' => $story->image_url,
                'status' => (string) $story->status,
                'admin_note' => $story->admin_note,
                'created_at' => optional($story->created_at)?->toIso8601String(),
                'approved_at' => optional($story->approved_at)?->toIso8601String(),
                'user' => $story->user ? [
                    'id' => (int) $story->user->id,
                    'name' => (string) $story->user->name,
                    'email' => (string) $story->user->email,
                    'role' => (string) $story->user->role,
                ] : null,
                'approver' => $story->approver ? [
                    'id' => (int) $story->approver->id,
                    'name' => (string) $story->approver->name,
                    'email' => (string) $story->approver->email,
                ] : null,
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => 'Basari hikayeleri hazir.',
            'data' => $stories,
        ]);
    }

    public function successStoriesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'sport' => ['required', 'string', 'max:80'],
            'story_text' => ['required', 'string', 'max:2500'],
            'old_club' => ['nullable', 'string', 'max:150'],
            'new_club' => ['nullable', 'string', 'max:150'],
            'image_url' => ['nullable', 'string', 'max:500'],
        ]);

        if (Schema::hasTable('success_stories')) {
            $story = SuccessStory::query()->create([
                'user_id' => (int) $request->user()->id,
                'full_name' => $data['full_name'],
                'sport' => $data['sport'],
                'story_text' => $data['story_text'],
                'old_club' => $data['old_club'] ?? null,
                'new_club' => $data['new_club'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Basari hikayesi onay bekliyor.',
                'data' => [
                    'id' => (int) $story->id,
                    'status' => (string) $story->status,
                ],
            ]);
        }

        $rows = Cache::get('legacy_success_stories', []);
        if (! is_array($rows)) {
            $rows = [];
        }

        array_unshift($rows, [
            'id' => time(),
            'full_name' => $data['full_name'],
            'sport' => $data['sport'],
            'story_text' => $data['story_text'],
            'old_club' => $data['old_club'] ?? null,
            'new_club' => $data['new_club'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'created_at' => now()->toIso8601String(),
        ]);
        Cache::put('legacy_success_stories', array_slice($rows, 0, 100), now()->addDays(30));

        return response()->json(['ok' => true, 'message' => 'Basari hikayesi kaydedildi.']);
    }

    public function adminSuccessStoriesUpdate(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasTable('success_stories')) {
            return response()->json([
                'ok' => false,
                'message' => 'Basari hikayeleri tablosu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:1500'],
        ]);

        $story = SuccessStory::query()->find($id);
        if (! $story) {
            return response()->json([
                'ok' => false,
                'message' => 'Basari hikayesi bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $story->status = $validated['status'];
        $story->admin_note = $validated['admin_note'] ?? null;

        if ($story->status === 'approved') {
            $story->approved_by = (int) $request->user()->id;
            $story->approved_at = now();
        } else {
            $story->approved_by = null;
            $story->approved_at = null;
        }

        $story->save();

        return response()->json([
            'ok' => true,
            'message' => 'Basari hikayesi guncellendi.',
            'data' => [
                'id' => (int) $story->id,
                'status' => (string) $story->status,
                'admin_note' => $story->admin_note,
                'approved_by' => $story->approved_by,
                'approved_at' => optional($story->approved_at)?->toIso8601String(),
            ],
        ]);
    }

    public function lawyerRegister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_number' => ['required', 'string', 'max:120'],
            'specialization' => ['required', 'string', 'max:160'],
            'years_experience' => ['required', 'integer', 'min:0', 'max:80'],
            'hourly_rate' => ['required'],
        ]);

        if (Schema::hasTable('lawyers')) {
            $lawyer = Lawyer::query()->updateOrCreate(
                ['user_id' => (int) $request->user()->id],
                [
                    'license_number' => $validated['license_number'],
                    'specialization' => $validated['specialization'],
                    'years_experience' => $validated['years_experience'],
                    'hourly_rate' => $validated['hourly_rate'],
                    'is_active' => true,
                    'license_status' => 'valid',
                ]
            );

            return response()->json([
                'ok' => true,
                'message' => 'Avukat profili kaydedildi.',
                'data' => $lawyer,
            ]);
        }

        $payload = [
            'user_id' => (int) $request->user()->id,
            'license_number' => $validated['license_number'],
            'specialization' => $validated['specialization'],
            'years_experience' => $validated['years_experience'],
            'created_at' => now()->toIso8601String(),
        ];
        Cache::put('legacy_lawyer_profile_' . $request->user()->id, $payload, now()->addDays(90));

        return response()->json(['ok' => true, 'message' => 'Avukat profili kaydedildi.', 'data' => $payload]);
    }

    public function profileCardLike(Request $request, string $cardType, int $cardOwnerId): JsonResponse
    {
        $key = sprintf('legacy_profile_like_%s_%d', $cardType, $cardOwnerId);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addDays(180));

        return response()->json([
            'ok' => true,
            'message' => 'Begeni kaydedildi.',
            'data' => [
                'card_type' => $cardType,
                'card_owner_id' => $cardOwnerId,
                'likes' => $count,
            ],
        ]);
    }
}
