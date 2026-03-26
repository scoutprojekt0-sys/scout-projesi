<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\PlayerTransfer;
use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeaturedController extends Controller
{
    use ApiResponds;

    public function getFeatured(): JsonResponse
    {
        $rows = DB::table('featured_content')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('featured_from')
                    ->orWhere('featured_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('featured_until')
                    ->orWhere('featured_until', '>=', now());
            })
            ->where('section', 'homepage')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $items = $rows->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'badge_text' => $item->badge_text,
                'badge_color' => $item->badge_color,
                'priority' => (int) $item->priority,
                'data' => $this->resolveFeaturedObject((string) $item->featurable_type, (int) $item->featurable_id),
            ];
        })->filter(fn ($item) => $item['data'] !== null)->values();

        return $this->successResponse($items, 'One cikan icerikler hazir.');
    }

    public function getRisingStars(): JsonResponse
    {
        $stars = DB::table('users')
            ->where('role', 'player')
            ->whereNotNull('age')
            ->where('age', '<=', 21)
            ->orderByDesc('rating')
            ->orderByDesc('views_count')
            ->limit(10)
            ->get([
                'id',
                'name',
                'position',
                'city',
                'age',
                'rating',
                'views_count',
            ]);

        return $this->successResponse($stars, 'Yukselen yildizlar hazir.');
    }

    public function getHotTransfers(): JsonResponse
    {
        $transfers = PlayerTransfer::query()
            ->with(['player:id,name', 'fromClub:id,name', 'toClub:id,name'])
            ->where('verification_status', 'verified')
            ->orderByDesc('fee')
            ->orderByDesc('transfer_date')
            ->limit(15)
            ->get()
            ->map(fn (PlayerTransfer $transfer) => [
                'id' => (int) $transfer->id,
                'player_name' => (string) ($transfer->player?->name ?? '-'),
                'from_club_name' => $transfer->fromClub?->name,
                'to_club_name' => $transfer->toClub?->name,
                'transfer_type' => (string) $transfer->transfer_type,
                'fee' => $transfer->fee !== null ? (float) $transfer->fee : null,
                'currency' => $transfer->currency,
                'transfer_date' => optional($transfer->transfer_date)?->toDateString(),
                'reliability_score' => (float) ($transfer->confidence_score ?? 0) * 100,
            ])
            ->values();

        return $this->successResponse($transfers, 'Sicak transfer listesi hazir.');
    }

    public function getPlayerOfWeek(): JsonResponse
    {
        $player = DB::table('users')
            ->where('role', 'player')
            ->orderByDesc('rating')
            ->orderByDesc('views_count')
            ->first([
                'id',
                'name',
                'position',
                'city',
                'age',
                'rating',
                'views_count',
            ]);

        return $this->successResponse($player, 'Haftanin oyuncusu hazir.');
    }

    public function adminList(Request $request): JsonResponse
    {
        $section = (string) $request->query('section', 'homepage');

        $rows = DB::table('featured_content')
            ->where('section', $section)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return $this->successResponse($rows, 'One cikan icerik listesi hazir.');
    }

    public function adminStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'featurable_type' => ['required', 'in:user,success_story,player_transfer,live_match'],
            'featurable_id' => ['required', 'integer', 'min:1'],
            'section' => ['required', 'in:homepage,players,clubs,news,videos'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'badge_text' => ['nullable', 'string', 'max:50'],
            'badge_color' => ['nullable', 'string', 'max:20'],
            'featured_from' => ['nullable', 'date'],
            'featured_until' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($this->resolveFeaturedObject($validated['featurable_type'], (int) $validated['featurable_id']) === null) {
            return $this->errorResponse('Gosterilecek kayit bulunamadi.', 422, 'featured_target_not_found');
        }

        $existing = DB::table('featured_content')
            ->where('featurable_type', $validated['featurable_type'])
            ->where('featurable_id', $validated['featurable_id'])
            ->where('section', $validated['section'])
            ->first();

        $payload = [
            'priority' => $validated['priority'] ?? 0,
            'badge_text' => $validated['badge_text'] ?? null,
            'badge_color' => $validated['badge_color'] ?? '#3B82F6',
            'featured_from' => $validated['featured_from'] ?? null,
            'featured_until' => $validated['featured_until'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('featured_content')->where('id', $existing->id)->update($payload);
            $id = (int) $existing->id;
            $status = 200;
        } else {
            $id = (int) DB::table('featured_content')->insertGetId(array_merge($payload, [
                'featurable_type' => $validated['featurable_type'],
                'featurable_id' => $validated['featurable_id'],
                'section' => $validated['section'],
                'created_at' => now(),
            ]));
            $status = 201;
        }

        return $this->successResponse(['id' => $id], 'One cikan icerik kaydedildi.', $status);
    }

    public function adminToggleActive(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $affected = DB::table('featured_content')
            ->where('id', $id)
            ->update([
                'is_active' => (bool) $validated['is_active'],
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            return $this->errorResponse('Kayit bulunamadi', 404, 'featured_not_found');
        }

        return $this->successResponse(null, 'Durum guncellendi');
    }

    private function resolveFeaturedObject(string $type, int $id): ?array
    {
        return match ($type) {
            'user' => $this->resolveUser($id),
            'success_story' => $this->resolveSuccessStory($id),
            'player_transfer' => $this->resolvePlayerTransfer($id),
            'live_match' => $this->resolveLiveMatch($id),
            default => null,
        };
    }

    private function resolveUser(int $id): ?array
    {
        $user = User::query()->find($id, ['id', 'name', 'role', 'position', 'city', 'age', 'rating', 'photo_url']);
        if (!$user) {
            return null;
        }

        return [
            'type' => 'user',
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => (string) $user->role,
            'position' => $user->position,
            'city' => $user->city,
            'age' => $user->age,
            'rating' => $user->rating !== null ? (float) $user->rating : null,
            'photo_url' => $user->photo_url,
        ];
    }

    private function resolveSuccessStory(int $id): ?array
    {
        $story = SuccessStory::query()->find($id);
        if (!$story) {
            return null;
        }

        return [
            'type' => 'success_story',
            'id' => (int) $story->id,
            'full_name' => (string) $story->full_name,
            'sport' => (string) $story->sport,
            'story_subject' => $story->story_subject,
            'old_club' => $story->old_club,
            'new_club' => $story->new_club,
            'story_text' => (string) $story->story_text,
            'image_url' => $story->image_url,
        ];
    }

    private function resolvePlayerTransfer(int $id): ?array
    {
        $transfer = PlayerTransfer::query()
            ->with(['player:id,name', 'fromClub:id,name', 'toClub:id,name'])
            ->find($id);
        if (!$transfer) {
            return null;
        }

        return [
            'type' => 'player_transfer',
            'id' => (int) $transfer->id,
            'player_name' => (string) ($transfer->player?->name ?? '-'),
            'from_club_name' => $transfer->fromClub?->name,
            'to_club_name' => $transfer->toClub?->name,
            'transfer_type' => (string) $transfer->transfer_type,
            'fee' => $transfer->fee !== null ? (float) $transfer->fee : null,
            'currency' => $transfer->currency,
            'transfer_date' => optional($transfer->transfer_date)?->toDateString(),
        ];
    }

    private function resolveLiveMatch(int $id): ?array
    {
        $match = LiveMatch::query()->find($id);
        if (!$match) {
            return null;
        }

        return [
            'type' => 'live_match',
            'id' => (int) $match->id,
            'title' => (string) $match->title,
            'league' => $match->league,
            'home_team' => $match->home_team,
            'away_team' => $match->away_team,
            'home_score' => $match->home_score,
            'away_score' => $match->away_score,
            'match_date' => optional($match->match_date)?->toIso8601String(),
        ];
    }
}
