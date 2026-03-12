<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrendingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_get_today_trending(): void
    {
        DB::table('trending_content')->insert([
            'trendable_type' => 'players',
            'trendable_id' => 12,
            'views_today' => 25,
            'views_week' => 25,
            'views_month' => 25,
            'clicks_today' => 7,
            'clicks_week' => 7,
            'clicks_month' => 7,
            'shares_count' => 2,
            'saves_count' => 1,
            'trending_score' => 40,
            'trending_date' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/trending/today?type=players')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.type', 'players')
            ->assertJsonPath('data.0.views_today', 25);
    }

    public function test_public_can_track_trending_interactions(): void
    {
        $this->postJson('/api/trending/track', [
            'type' => 'players',
            'id' => 42,
            'action' => 'view',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('trending_content', [
            'trendable_type' => 'players',
            'trendable_id' => 42,
            'views_today' => 1,
        ]);
    }
}
