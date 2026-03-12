<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrendingController extends Controller
{
    use ApiResponds;
    public function getTodayTrending(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', 'all');

        $query = DB::table('trending_content')
            ->where('trending_date', today()->toDateString())
            ->orderByDesc('trending_score');

        if ($type !== 'all') {
            $query->where('trendable_type', 'like', '%'.$type.'%');
        }

        $items = $query
            ->limit(20)
            ->get([
                'trendable_id',
                'trendable_type',
                'views_today',
                'clicks_today',
                'shares_count',
                'saves_count',
                'trending_score',
            ])
            ->map(fn ($item) => [
                'id' => (int) $item->trendable_id,
                'type' => (string) $item->trendable_type,
                'views_today' => (int) $item->views_today,
                'clicks_today' => (int) $item->clicks_today,
                'shares_count' => (int) $item->shares_count,
                'saves_count' => (int) $item->saves_count,
                'trending_score' => (float) $item->trending_score,
            ])
            ->values();

        return $this->successResponse($items, 'Trend icerikler hazir.');
    }

    public function trackInteraction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:view,click,share,save'],
        ]);

        $base = [
            'trendable_type' => $validated['type'],
            'trendable_id' => $validated['id'],
            'trending_date' => today()->toDateString(),
        ];

        $existing = DB::table('trending_content')->where($base)->first();

        if (! $existing) {
            DB::table('trending_content')->insert(array_merge($base, [
                'views_today' => 0,
                'views_week' => 0,
                'views_month' => 0,
                'clicks_today' => 0,
                'clicks_week' => 0,
                'clicks_month' => 0,
                'shares_count' => 0,
                'saves_count' => 0,
                'trending_score' => 0,
                'last_viewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $updates = match ($validated['action']) {
            'view' => [
                'views_today' => DB::raw('views_today + 1'),
                'views_week' => DB::raw('views_week + 1'),
                'views_month' => DB::raw('views_month + 1'),
                'trending_score' => DB::raw('trending_score + 1'),
                'last_viewed_at' => now(),
                'updated_at' => now(),
            ],
            'click' => [
                'clicks_today' => DB::raw('clicks_today + 1'),
                'clicks_week' => DB::raw('clicks_week + 1'),
                'clicks_month' => DB::raw('clicks_month + 1'),
                'trending_score' => DB::raw('trending_score + 2'),
                'updated_at' => now(),
            ],
            'share' => [
                'shares_count' => DB::raw('shares_count + 1'),
                'trending_score' => DB::raw('trending_score + 5'),
                'updated_at' => now(),
            ],
            default => [
                'saves_count' => DB::raw('saves_count + 1'),
                'trending_score' => DB::raw('trending_score + 3'),
                'updated_at' => now(),
            ],
        };

        DB::table('trending_content')->where($base)->update($updates);

        return $this->successResponse(null, 'Etkilesim kaydedildi.');
    }
}
