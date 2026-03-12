<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NewsController extends Controller
{
    use ApiResponds;

    public function index(): JsonResponse
    {
        // Return news/opportunities list for general news feed
        $opportunities = DB::table('opportunities')
            ->leftJoin('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->where('opportunities.status', 'open')
            ->orderByDesc('opportunities.created_at')
            ->paginate(20, [
                'opportunities.id',
                'opportunities.title',
                'opportunities.position',
                'opportunities.city',
                'opportunities.created_at as published_at',
                'teams.name as source',
            ]);

        return $this->paginatedListResponse($opportunities, 'Haberler hazir.');
    }

    public function live(): JsonResponse
    {
        $rows = DB::table('opportunities')
            ->leftJoin('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->where('opportunities.status', 'open')
            ->orderByDesc('opportunities.created_at')
            ->limit(5)
            ->get(['opportunities.id','opportunities.title','opportunities.created_at as published_at','teams.name as source']);

        if ($rows->isEmpty()) {
            return $this->successResponse([[
                'id' => 0,
                'title' => 'Scout aginda yeni transfer firsatlari acildi.',
                'source' => 'NextScout',
                'published_at' => now()->toISOString(),
            ]], 'Canli haberler hazir.');
        }

        return $this->successResponse(
            $rows->map(fn ($row) => [
                'id'           => (int) $row->id,
                'title'        => (string) $row->title,
                'source'       => $row->source ?: 'Kulup',
                'published_at' => (string) $row->published_at,
            ])->values(),
            'Canli haberler hazir.'
        );
    }
}
