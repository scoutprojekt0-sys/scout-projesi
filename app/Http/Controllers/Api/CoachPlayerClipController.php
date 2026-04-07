<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\CoachPlayerClip;
use App\Models\User;
use App\Models\VideoAnalysis;
use App\Models\VideoClip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoachPlayerClipController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array((string) $user->role, ['coach', 'manager', 'scout', 'club', 'team'], true)) {
            return $this->errorResponse('Bu alan sadece teknik ekip kullanicilari icindir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'scope' => ['nullable', 'in:mine,team'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 50);
        $scope = (string) ($validated['scope'] ?? 'mine');

        $query = CoachPlayerClip::query()
            ->with(['author', 'video', 'analysis'])
            ->latest('id');

        if ($scope !== 'team') {
            $query->where('coach_user_id', $user->id);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('player_name', 'like', '%'.$search.'%')
                    ->orWhere('video_url', 'like', '%'.$search.'%')
                    ->orWhere('stamp', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }

        $clips = $query->paginate($perPage)->through(fn (CoachPlayerClip $clip) => $this->transformClip($clip));

        return $this->successResponse($clips, 'Klip notlari hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array((string) $user->role, ['coach', 'manager', 'scout', 'club', 'team'], true)) {
            return $this->errorResponse('Bu alan sadece teknik ekip kullanicilari icindir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'player_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'player_name' => ['required', 'string', 'min:2', 'max:120'],
            'video_clip_id' => ['nullable', 'integer', 'exists:video_clips,id'],
            'video_url' => ['required_without:video_clip_id', 'nullable', 'url', 'max:2000'],
            'minute_mark' => ['nullable', 'integer', 'min:0', 'max:180'],
            'second_mark' => ['nullable', 'integer', 'min:0', 'max:59'],
            'start_second' => ['nullable', 'integer', 'min:0', 'max:10800'],
            'end_second' => ['nullable', 'integer', 'min:0', 'max:10800'],
            'shared_roles' => ['nullable', 'array'],
            'shared_roles.*' => ['string', 'in:scout,manager,coach,club,team'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
        ]);

        $playerUserId = isset($validated['player_user_id']) ? (int) $validated['player_user_id'] : null;
        $videoClipId = isset($validated['video_clip_id']) ? (int) $validated['video_clip_id'] : null;
        $videoClip = $videoClipId ? VideoClip::query()->find($videoClipId) : null;

        if ($playerUserId !== null) {
            $player = User::query()->find($playerUserId);
            if (! $player || (string) $player->role !== 'player') {
                return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
            }
        } elseif ($videoClip) {
            $playerUserId = (int) $videoClip->user_id;
        }

        $minute = max(0, (int) ($validated['minute_mark'] ?? 0));
        $second = max(0, min(59, (int) ($validated['second_mark'] ?? 0)));
        $stamp = sprintf('%02d:%02d', $minute, $second);
        $startSecond = isset($validated['start_second'])
            ? max(0, (int) $validated['start_second'])
            : ($minute * 60) + $second;
        $endSecond = isset($validated['end_second'])
            ? max(0, (int) $validated['end_second'])
            : null;

        if ($endSecond !== null && $endSecond < $startSecond) {
            return $this->errorResponse('Bitis saniyesi baslangic saniyesinden kucuk olamaz.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_clip_range');
        }

        $videoUrl = trim((string) ($validated['video_url'] ?? $videoClip?->video_url ?? ''));
        if ($videoUrl === '') {
            return $this->errorResponse('Klip icin video gerekli.', Response::HTTP_UNPROCESSABLE_ENTITY, 'video_source_required');
        }

        $sharedRoles = collect($validated['shared_roles'] ?? [])
            ->map(static fn ($value) => strtolower(trim((string) $value)))
            ->filter(static fn ($value) => in_array($value, ['scout', 'manager', 'coach', 'club', 'team'], true))
            ->unique()
            ->values()
            ->all();

        $analysis = null;
        if ($videoClipId !== null) {
            $analysis = VideoAnalysis::query()
                ->where('video_clip_id', $videoClipId)
                ->where('status', 'completed')
                ->latest('id')
                ->first();
        }

        $rangeLabel = $this->formatRangeLabel($startSecond, $endSecond);

        $clip = CoachPlayerClip::query()->create([
            'coach_user_id' => $user->id,
            'player_user_id' => $playerUserId,
            'player_name' => trim((string) $validated['player_name']),
            'video_clip_id' => $videoClipId,
            'video_analysis_id' => $analysis?->id,
            'video_url' => $videoUrl,
            'minute_mark' => $minute,
            'second_mark' => $second,
            'start_second' => $startSecond,
            'end_second' => $endSecond,
            'stamp' => $stamp,
            'range_label' => $rangeLabel,
            'shared_roles' => $sharedRoles,
            'analysis_summary' => $analysis?->summary,
            'body' => trim((string) $validated['body']),
        ]);

        return $this->successResponse(
            $this->transformClip($clip->fresh(['author', 'video', 'analysis'])),
            'Klip notu kaydedildi.',
            Response::HTTP_CREATED
        );
    }

    public function analyze(Request $request, int $clipId): JsonResponse
    {
        $user = $request->user();

        if (! in_array((string) $user->role, ['coach', 'manager', 'scout', 'club', 'team'], true)) {
            return $this->errorResponse('Bu alan sadece teknik ekip kullanicilari icindir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $clip = CoachPlayerClip::query()
            ->with(['author', 'video', 'analysis', 'analysis.events'])
            ->findOrFail($clipId);

        $analysis = $clip->analysis;
        if (! $analysis && $clip->video_clip_id) {
            $analysis = VideoAnalysis::query()
                ->with('events')
                ->where('video_clip_id', $clip->video_clip_id)
                ->where('status', 'completed')
                ->latest('id')
                ->first();
        }

        if (! $analysis) {
            return $this->errorResponse('Klip icin kullanilabilir bir video analizi bulunamadi.', Response::HTTP_UNPROCESSABLE_ENTITY, 'analysis_not_found');
        }

        $startSecond = $clip->start_second ?? (($clip->minute_mark ?? 0) * 60) + ($clip->second_mark ?? 0);
        $endSecond = $clip->end_second ?? ($startSecond + 12);

        $events = $analysis->events()
            ->where(function ($query) use ($startSecond, $endSecond) {
                $query
                    ->whereBetween('start_second', [$startSecond, $endSecond])
                    ->orWhereBetween('end_second', [$startSecond, $endSecond])
                    ->orWhere(function ($inner) use ($startSecond, $endSecond) {
                        $inner
                            ->where('start_second', '<=', $startSecond)
                            ->where('end_second', '>=', $endSecond);
                    });
            })
            ->get();

        $eventCount = $events->count();
        $topEvent = (string) ($events->groupBy('event_type')->sortByDesc->count()->keys()->first() ?? 'none');
        $averageConfidence = round((float) ($events->avg('confidence') ?? 0), 2);
        $eventTypes = $events->pluck('event_type')->filter()->unique()->values()->all();
        $metric = $analysis->metrics()
            ->when($clip->player_user_id, fn ($query) => $query->where('player_id', $clip->player_user_id))
            ->latest('id')
            ->first();

        $summary = array_filter([
            'source' => 'clip_specific_from_video_analysis',
            'clip_window' => $this->formatRangeLabel($startSecond, $endSecond),
            'event_count' => $eventCount,
            'top_event' => $topEvent !== 'none' ? $topEvent : null,
            'event_types' => $eventTypes ?: null,
            'average_confidence' => $eventCount > 0 ? $averageConfidence : null,
            'speed_score' => $metric?->speed_score,
            'movement_score' => $metric?->movement_score,
            'cross_quality_score' => $metric?->cross_quality_score,
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');

        $clip->update([
            'video_analysis_id' => $analysis->id,
            'analysis_summary' => $summary,
        ]);

        return $this->successResponse(
            $this->transformClip($clip->fresh(['author', 'video', 'analysis'])),
            'Klip icin AI ozeti guncellendi.'
        );
    }

    private function transformClip(CoachPlayerClip $clip): array
    {
        return [
            'id' => $clip->id,
            'player_user_id' => $clip->player_user_id,
            'player' => $clip->player_name,
            'video_clip_id' => $clip->video_clip_id,
            'video_analysis_id' => $clip->video_analysis_id,
            'url' => $clip->video_url,
            'minute_mark' => $clip->minute_mark,
            'second_mark' => $clip->second_mark,
            'stamp' => $clip->stamp,
            'start_second' => $clip->start_second,
            'end_second' => $clip->end_second,
            'range_label' => $clip->range_label,
            'shared_roles' => $clip->shared_roles ?? [],
            'analysis_summary' => $clip->analysis_summary,
            'body' => $clip->body,
            'author_name' => $clip->author?->name,
            'author_role' => $clip->author?->role,
            'is_mine' => (int) $clip->coach_user_id === (int) optional(request()->user())->id,
            'created_at' => optional($clip->created_at)->toIso8601String(),
        ];
    }

    private function formatRangeLabel(int $startSecond, ?int $endSecond): string
    {
        $start = sprintf('%02d:%02d', intdiv($startSecond, 60), $startSecond % 60);
        if ($endSecond === null) {
            return $start;
        }

        $end = sprintf('%02d:%02d', intdiv($endSecond, 60), $endSecond % 60);

        return $start.' - '.$end;
    }
}
