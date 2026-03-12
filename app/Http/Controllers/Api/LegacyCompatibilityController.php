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
        $rows = DB::table('users')
            ->where('role', 'player')
            ->orderByDesc('views_count')
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get([
                'id',
                'name',
                'position',
                'city',
                DB::raw("COALESCE(source_url, 'Sponsorlu oyuncu profili') as summary"),
                DB::raw("'Sponsorlu' as package_label"),
                'created_at',
                'updated_at',
            ]);

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
                // Legacy mobile flow expects newly added stories to appear immediately.
                'status' => 'approved',
                'approved_by' => (int) $request->user()->id,
                'approved_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Basari hikayesi kaydedildi.',
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
