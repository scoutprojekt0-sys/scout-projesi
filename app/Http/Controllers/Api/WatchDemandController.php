<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\LiveWatchRequest;
use App\Models\PlayerMatchSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchDemandController extends Controller
{
    use ApiResponds;

    public function publicHeatmap(Request $request): JsonResponse
    {
        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : now()->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : now()->copy()->addDays(7)->endOfDay();

        $rows = PlayerMatchSchedule::query()
            ->with('player:id,name,city,photo_url')
            ->where('is_public', true)
            ->whereBetween('match_date', [$from, $to])
            ->orderBy('match_date')
            ->get();

        $hotspots = $rows
            ->groupBy(function (PlayerMatchSchedule $schedule) {
                return implode('|', [
                    mb_strtolower((string) $schedule->city),
                    mb_strtolower((string) ($schedule->district ?? '')),
                ]);
            })
            ->map(function ($items) {
                $first = $items->first();
                $positions = collect($items)
                    ->map(fn ($item) => $this->normalizePosition($item->position))
                    ->filter()
                    ->countBy()
                    ->sortDesc();

                return [
                    'city' => $first?->city ?: 'Bilinmiyor',
                    'district' => $first?->district ?: null,
                    'match_count' => $items->count(),
                    'player_count' => $items->pluck('player_user_id')->filter()->unique()->count(),
                    'top_position' => $positions->keys()->first() ?: 'Pozisyon belirtilmedi',
                    'intensity' => min(100, max(18, $items->count() * 18)),
                    'latitude' => $first?->latitude,
                    'longitude' => $first?->longitude,
                    'sample_profiles' => $items->take(3)->map(function ($item) {
                        return [
                            'player_id' => (int) $item->player_user_id,
                            'name' => $item->player?->name ?: 'Oyuncu',
                            'position' => $this->normalizePosition($item->position),
                            'match_title' => $item->match_title,
                            'kickoff' => $item->match_date?->toIso8601String(),
                        ];
                    })->values(),
                ];
            })
            ->sortByDesc('match_count')
            ->take(6)
            ->values();

        $summary = [
            'total_public_matches' => $rows->count(),
            'active_players' => $rows->pluck('player_user_id')->filter()->unique()->count(),
            'hotspot_count' => $hotspots->count(),
            'window_days' => $from->diffInDays($to) + 1,
        ];

        return $this->successResponse([
            'summary' => $summary,
            'hotspots' => $hotspots,
        ], 'Canli izleme isi haritasi hazir.');
    }

    public function mySchedules(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'player') {
            return $this->errorResponse('Bu alan sadece oyuncular icin aktif.', 403, 'forbidden');
        }

        $rows = PlayerMatchSchedule::query()
            ->where('player_user_id', $user->id)
            ->orderBy('match_date')
            ->get()
            ->map(fn (PlayerMatchSchedule $row) => $this->transformSchedule($row))
            ->values();

        return $this->successResponse($rows, 'Oyuncu mac takvimi hazir.');
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'player') {
            return $this->errorResponse('Bu alan sadece oyuncular icin aktif.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'match_title' => ['required', 'string', 'max:160'],
            'team_name' => ['nullable', 'string', 'max:120'],
            'opponent_name' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:60'],
            'match_date' => ['required', 'date'],
            'city' => ['required', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'venue' => ['nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_public' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $schedule = PlayerMatchSchedule::query()->create([
            ...$validated,
            'player_user_id' => $user->id,
            'is_public' => array_key_exists('is_public', $validated) ? (bool) $validated['is_public'] : true,
        ]);

        return $this->successResponse($this->transformSchedule($schedule), 'Mac takvimi kaydedildi.', 201);
    }

    public function deleteSchedule(Request $request, int $scheduleId): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'player') {
            return $this->errorResponse('Bu alan sadece oyuncular icin aktif.', 403, 'forbidden');
        }

        $schedule = PlayerMatchSchedule::query()
            ->where('player_user_id', $user->id)
            ->findOrFail($scheduleId);

        $schedule->delete();

        return $this->successResponse(['id' => $scheduleId], 'Mac kaydi silindi.');
    }

    public function myRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['manager', 'coach'], true)) {
            return $this->errorResponse('Bu alan sadece menajer ve antrenor icin aktif.', 403, 'forbidden');
        }

        $rows = LiveWatchRequest::query()
            ->where('requester_user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LiveWatchRequest $row) => $this->transformWatchRequest($row))
            ->values();

        return $this->successResponse($rows, 'Canli izleme talepleri hazir.');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['manager', 'coach'], true)) {
            return $this->errorResponse('Bu alan sadece menajer ve antrenor icin aktif.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'target_date' => ['required', 'date'],
            'city' => ['required', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'position' => ['nullable', 'string', 'max:60'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:250'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $watchRequest = LiveWatchRequest::query()->create([
            ...$validated,
            'requester_user_id' => $user->id,
            'requester_role' => $user->role,
            'radius_km' => (int) ($validated['radius_km'] ?? 20),
            'status' => 'open',
        ]);

        return $this->successResponse($this->transformWatchRequest($watchRequest), 'Canli izleme talebi olusturuldu.', 201);
    }

    public function resolveRequest(Request $request, int $requestId): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['manager', 'coach'], true)) {
            return $this->errorResponse('Bu alan sadece menajer ve antrenor icin aktif.', 403, 'forbidden');
        }

        $watchRequest = LiveWatchRequest::query()
            ->where('requester_user_id', $user->id)
            ->findOrFail($requestId);

        $query = PlayerMatchSchedule::query()
            ->with('player:id,name,city,age,photo_url,position')
            ->where('is_public', true)
            ->whereDate('match_date', $watchRequest->target_date->toDateString())
            ->where('city', 'like', $watchRequest->city);

        if ($watchRequest->district) {
            $query->where(function ($builder) use ($watchRequest) {
                $builder->where('district', 'like', $watchRequest->district)
                    ->orWhereNull('district');
            });
        }

        $rows = $query->get()
            ->map(function (PlayerMatchSchedule $schedule) use ($watchRequest) {
                $score = 50;
                $position = $this->normalizePosition($schedule->position ?: $schedule->player?->position);
                if ($watchRequest->position && mb_strtolower($position) === mb_strtolower($this->normalizePosition($watchRequest->position))) {
                    $score += 28;
                }
                if ($watchRequest->district && $schedule->district && mb_strtolower((string) $schedule->district) === mb_strtolower((string) $watchRequest->district)) {
                    $score += 12;
                }
                if ($schedule->latitude && $schedule->longitude) {
                    $score += 5;
                }
                if ($schedule->player?->age && $schedule->player->age <= 24) {
                    $score += 5;
                }

                return [
                    'schedule_id' => (int) $schedule->id,
                    'player_id' => (int) $schedule->player_user_id,
                    'player_name' => $schedule->player?->name ?: 'Oyuncu',
                    'player_age' => $schedule->player?->age,
                    'position' => $position,
                    'match_title' => $schedule->match_title,
                    'team_name' => $schedule->team_name,
                    'opponent_name' => $schedule->opponent_name,
                    'city' => $schedule->city,
                    'district' => $schedule->district,
                    'venue' => $schedule->venue,
                    'kickoff' => $schedule->match_date?->toIso8601String(),
                    'latitude' => $schedule->latitude,
                    'longitude' => $schedule->longitude,
                    'notes' => $schedule->notes,
                    'match_score' => min(99, $score),
                    'fit_reason' => $this->buildFitReason($watchRequest, $schedule, $position),
                ];
            })
            ->sortByDesc('match_score')
            ->values();

        return $this->successResponse([
            'request' => $this->transformWatchRequest($watchRequest),
            'matches' => $rows,
        ], 'Canli izleme eslesmeleri hazir.');
    }

    private function transformSchedule(PlayerMatchSchedule $row): array
    {
        return [
            'id' => (int) $row->id,
            'player_user_id' => (int) $row->player_user_id,
            'match_title' => $row->match_title,
            'team_name' => $row->team_name,
            'opponent_name' => $row->opponent_name,
            'position' => $this->normalizePosition($row->position),
            'match_date' => $row->match_date?->toIso8601String(),
            'city' => $row->city,
            'district' => $row->district,
            'venue' => $row->venue,
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
            'is_public' => (bool) $row->is_public,
            'notes' => $row->notes,
        ];
    }

    private function transformWatchRequest(LiveWatchRequest $row): array
    {
        return [
            'id' => (int) $row->id,
            'requester_user_id' => (int) $row->requester_user_id,
            'requester_role' => $row->requester_role,
            'target_date' => $row->target_date?->toDateString(),
            'city' => $row->city,
            'district' => $row->district,
            'position' => $this->normalizePosition($row->position),
            'radius_km' => (int) $row->radius_km,
            'notes' => $row->notes,
            'status' => $row->status,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function normalizePosition(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 'Pozisyon belirtilmedi';
        }

        $compact = mb_strtolower($raw);
        return match (true) {
            str_contains($compact, 'stoper'), str_contains($compact, 'center back'), str_contains($compact, 'cb') => 'Stoper',
            str_contains($compact, 'sol bek'), str_contains($compact, 'left back'), str_contains($compact, 'lb') => 'Sol Bek',
            str_contains($compact, 'sağ bek'), str_contains($compact, 'sag bek'), str_contains($compact, 'right back'), str_contains($compact, 'rb') => 'Sağ Bek',
            str_contains($compact, 'bek') => 'Bek',
            str_contains($compact, 'kanat'), str_contains($compact, 'wing') => 'Kanat',
            str_contains($compact, 'forvet'), str_contains($compact, 'santrfor'), str_contains($compact, 'striker'), str_contains($compact, 'forward') => 'Forvet',
            str_contains($compact, 'orta saha'), str_contains($compact, 'midfield'), str_contains($compact, '6 numara'), str_contains($compact, '8 numara'), str_contains($compact, '10 numara') => 'Orta Saha',
            str_contains($compact, 'kaleci'), str_contains($compact, 'goalkeeper') => 'Kaleci',
            default => $raw,
        };
    }

    private function buildFitReason(LiveWatchRequest $request, PlayerMatchSchedule $schedule, string $position): string
    {
        $reasons = [];
        if ($request->position && mb_strtolower($this->normalizePosition($request->position)) === mb_strtolower($position)) {
            $reasons[] = 'pozisyon';
        }
        if ($request->district && $schedule->district && mb_strtolower((string) $request->district) === mb_strtolower((string) $schedule->district)) {
            $reasons[] = 'bölge';
        }
        if ($schedule->latitude && $schedule->longitude) {
            $reasons[] = 'harita';
        }

        if (! count($reasons)) {
            return 'Tarih ve şehir filtresine uygun canlı maç kaydı bulundu.';
        }

        return implode(', ', $reasons) . ' filtresiyle güçlü eşleşme sağlandı.';
    }
}
