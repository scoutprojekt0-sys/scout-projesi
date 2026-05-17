<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketTerminalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_feed_marks_scout_video_entries_as_staff_profiles(): void
    {
        $scout = User::factory()->create([
            'role' => 'scout',
            'name' => 'Scout 01 Lia',
        ]);

        DB::table('video_clips')->insert([
            'user_id' => $scout->id,
            'title' => 'Scout video',
            'description' => 'Scout highlight upload',
            'video_url' => 'https://example.com/scout-video.mp4',
            'platform' => 'custom',
            'view_count' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/market/live-feed');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.top_videos.0.role', 'scout')
            ->assertJsonPath('data.top_videos.0.staff_id', $scout->id)
            ->assertJsonPath('data.top_videos.0.player_id', null)
            ->assertJsonPath('data.top_videos.0.name', 'Scout 01 Lia')
            ->assertJsonPath('data.top_videos.0.player_name', 'Scout 01 Lia')
            ->assertJsonPath('data.top_videos.0.position', 'Scout');
    }
}
