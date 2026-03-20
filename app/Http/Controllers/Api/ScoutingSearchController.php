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
