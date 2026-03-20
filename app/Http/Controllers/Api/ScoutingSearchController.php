<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\PlayerVideoMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoutingSearchController extends Controller
{
    use ApiResponds;

    public function discovery(Request $request): JsonResponse
    {
        $latestMetricIds = PlayerVideoMetric::query()
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('player_id')
            ->groupBy('player_id');

        $query = PlayerVideoMetric::query()
            ->joinSub($latestMetricIds, 'latest_metrics', function ($join) {
                $join->on('player_video_metrics.id', '=', 'latest_metrics.id');
            })
            ->join('users', 'users.id', '=', 'player_video_metrics.player_id')
            ->leftJoin('video_analyses', 'video_analyses.id', '=', 'player_video_metrics.video_analysis_id')
            ->leftJoin('video_clips', 'video_clips.id', '=', 'video_analyses.video_clip_id')
            ->where('users.role', 'player')
            ->select([
                'player_video_metrics.id',
                'player_video_metrics.player_id',
                'users.name',
                'users.city',
                'users.position',
                'users.age',
                'users.photo_url',
                'player_video_metrics.successful_passes',
                'player_video_metrics.successful_crosses',
                'player_video_metrics.speed_score',
                'player_video_metrics.movement_score',
                'player_video_metrics.cross_quality_score',
                'player_video_metrics.dribbles',
                'player_video_metrics.shots',
                'player_video_metrics.created_at as metric_created_at',
                'video_clips.id as video_clip_id',
                'video_clips.title as video_title',
                'video_clips.video_url',
            ]);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('users.name', 'like', "%{$search}%");
        }

        if ($request->filled('city')) {
            $query->where('users.city', (string) $request->input('city'));
        }

        if ($request->filled('position')) {
            $query->where('users.position', (string) $request->input('position'));
        }

        if ($request->filled('min_speed_score')) {
            $query->where('player_video_metrics.speed_score', '>=', (int) $request->input('min_speed_score'));
        }

        if ($request->filled('min_movement_score')) {
            $query->where('player_video_metrics.movement_score', '>=', (int) $request->input('min_movement_score'));
        }

        if ($request->filled('min_successful_crosses')) {
            $query->where('player_video_metrics.successful_crosses', '>=', (int) $request->input('min_successful_crosses'));
        }

        if ($request->filled('min_successful_passes')) {
            $query->where('player_video_metrics.successful_passes', '>=', (int) $request->input('min_successful_passes'));
        }

        $sort = (string) $request->input('sort', 'speed_score_desc');
        match ($sort) {
            'successful_crosses_desc' => $query->orderByDesc('player_video_metrics.successful_crosses'),
            'successful_passes_desc' => $query->orderByDesc('player_video_metrics.successful_passes'),
            'movement_score_desc' => $query->orderByDesc('player_video_metrics.movement_score'),
            'cross_quality_desc' => $query->orderByDesc('player_video_metrics.cross_quality_score'),
            'latest_desc' => $query->orderByDesc('player_video_metrics.id'),
            default => $query->orderByDesc('player_video_metrics.speed_score'),
        };

        return $this->paginatedListResponse(
            $query->paginate((int) $request->input('per_page', 12)),
            'AI scouting discovery listesi hazir.'
        );
    }

    public function rankings(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 5), 10));
        $latestMetricIds = PlayerVideoMetric::query()
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('player_id')
            ->groupBy('player_id');

        $base = PlayerVideoMetric::query()
            ->joinSub($latestMetricIds, 'latest_metrics', function ($join) {
                $join->on('player_video_metrics.id', '=', 'latest_metrics.id');
            })
            ->join('users', 'users.id', '=', 'player_video_metrics.player_id')
            ->where('users.role', 'player');

        $makeRows = function (string $orderColumn) use ($base, $limit) {
            return (clone $base)
                ->leftJoin('video_analyses', 'video_analyses.id', '=', 'player_video_metrics.video_analysis_id')
                ->leftJoin('video_clips', 'video_clips.id', '=', 'video_analyses.video_clip_id')
                ->orderByDesc($orderColumn)
                ->limit($limit)
                ->get([
                    'player_video_metrics.player_id',
                    'users.name',
                    'users.city',
                    'users.position',
                    'player_video_metrics.successful_crosses',
                    'player_video_metrics.successful_passes',
                    'player_video_metrics.speed_score',
                    'player_video_metrics.movement_score',
                    'player_video_metrics.cross_quality_score',
                    'video_clips.id as video_clip_id',
                    'video_clips.title as video_title',
                    'video_clips.video_url',
                ]);
        };

        return $this->successResponse([
            'best_crosses' => $makeRows('player_video_metrics.successful_crosses'),
            'best_speed' => $makeRows('player_video_metrics.speed_score'),
            'best_movement' => $makeRows('player_video_metrics.movement_score'),
        ], 'AI scouting ranking listeleri hazir.');
    }

    public function videoMetrics(Request $request): JsonResponse
    {
        $query = PlayerVideoMetric::query()
            ->with(['player:id,name,city', 'videoAnalysis.videoClip:id,title,video_url']);

        if ($request->filled('player_id')) {
            $query->where('player_id', (int) $request->input('player_id'));
        }

        if ($request->filled('min_successful_crosses')) {
            $query->where('successful_crosses', '>=', (int) $request->input('min_successful_crosses'));
        }

        if ($request->filled('min_successful_passes')) {
            $query->where('successful_passes', '>=', (int) $request->input('min_successful_passes'));
        }

        if ($request->filled('min_speed_score')) {
            $query->where('speed_score', '>=', (int) $request->input('min_speed_score'));
        }

        $sort = (string) $request->input('sort', 'created_at_desc');
        match ($sort) {
            'successful_crosses_desc' => $query->orderByDesc('successful_crosses'),
            'successful_passes_desc' => $query->orderByDesc('successful_passes'),
            'speed_score_desc' => $query->orderByDesc('speed_score'),
            default => $query->latest('id'),
        };

        return $this->paginatedListResponse(
            $query->paginate((int) $request->input('per_page', 20)),
            'Video metric scouting search hazir.'
        );
    }
}
