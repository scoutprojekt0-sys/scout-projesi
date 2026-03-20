<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\PlayerVideoMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerVideoMetricController extends Controller
{
    use ApiResponds;

    public function index(Request $request, int $playerId): JsonResponse
    {
        return $this->paginatedListResponse(
            PlayerVideoMetric::query()
                ->with(['videoAnalysis.videoClip:id,title,video_url'])
                ->where('player_id', $playerId)
                ->latest('id')
                ->paginate((int) $request->input('per_page', 20)),
            'Oyuncu video metrikleri hazir.'
        );
    }
}
