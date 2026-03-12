<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\PlayerMarketValue;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Week8TransparencyController extends Controller
{
    use ApiResponds;

    public function sourceHealth(): JsonResponse
    {
        $players = User::query()->where('role', 'player');

        $total = (clone $players)->count();
        $withSource = (clone $players)->where('has_source', true)->count();
        $missingSource = (clone $players)->where('has_source', false)->count();
        $lowConfidence = (clone $players)->where('confidence_score', '<', 0.60)->count();
        $needsReview = (clone $players)->where('verification_status', 'needs_review')->count();
        $verified = (clone $players)->where('verification_status', 'verified')->count();

        return $this->successResponse([
            'players_total' => $total,
            'with_source' => $withSource,
            'missing_source' => $missingSource,
            'low_confidence' => $lowConfidence,
            'needs_review' => $needsReview,
            'verified' => $verified,
            'source_coverage_percent' => $total > 0 ? round(($withSource / $total) * 100, 2) : 0,
            'generated_at' => now()->toIso8601String(),
        ], 'Kaynak saglik raporu hazir.');
    }

    public function players(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', 'player')
            ->select(['id','name','email','position','age','source_url','confidence_score',
                      'verification_status','has_source','has_conflicts','updated_at'])
            ->orderByDesc('updated_at');

        if ($request->filled('verification_status')) { $query->where('verification_status', $request->string('verification_status')); }
        if ($request->boolean('missing_source'))      { $query->where('has_source', false); }
        if ($request->filled('max_confidence'))       { $query->where('confidence_score', '<=', (float) $request->input('max_confidence')); }
        if ($request->filled('position'))             { $query->where('position', $request->string('position')); }

        $rows = $query->paginate((int) $request->input('per_page', 20));

        return $this->successResponse($rows, 'Oyuncu seffaflik listesi hazir.');
    }

    public function playerDetail(int $playerId): JsonResponse
    {
        $player = User::query()->where('id', $playerId)->where('role', 'player')->first();

        if (! $player) {
            return $this->errorResponse('Oyuncu bulunamadi.', 404, 'player_not_found');
        }

        $marketValues = PlayerMarketValue::query()
            ->where('player_id', $playerId)->orderByDesc('valuation_date')->limit(10)
            ->get(['id','value','currency','valuation_date','source_url','confidence_score','verification_status','model_version']);

        $transfers = PlayerTransfer::query()
            ->where('player_id', $playerId)->with(['fromClub:id,name','toClub:id,name'])->orderByDesc('transfer_date')->limit(10)
            ->get(['id','player_id','from_club_id','to_club_id','fee','currency','transfer_date',
                   'transfer_type','source_url','confidence_score','verification_status']);

        return $this->successResponse([
            'player' => [
                'id' => $player->id, 'name' => $player->name,
                'position' => $player->position, 'age' => $player->age,
                'source_url' => $player->source_url, 'confidence_score' => $player->confidence_score,
                'verification_status' => $player->verification_status,
                'has_source' => (bool) $player->has_source,
                'has_conflicts' => (bool) $player->has_conflicts,
            ],
            'market_values' => $marketValues,
            'transfers' => $transfers,
        ], 'Oyuncu seffaflik detayi hazir.');
    }
}
