<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ScoutReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoutRewardController extends Controller
{
    use ApiResponds;

    public function my(Request $request): JsonResponse
    {
        return $this->paginatedListResponse(
            ScoutReward::query()
                ->with(['scoutTip:id,player_name,status,signed_at'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->paginate((int) $request->input('per_page', 20)),
            'Scout odulleriniz hazir.'
        );
    }
}
