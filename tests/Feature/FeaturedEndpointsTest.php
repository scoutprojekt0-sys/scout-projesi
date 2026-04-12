<?php

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\PlayerTransfer;
use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeaturedEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_get_featured_homepage_items_and_discovery_lists(): void
    {
        $player = User::factory()->create([
            'role' => 'player',
            'name' => 'Rising Star',
            'age' => 20,
            'rating' => 8.7,
            'views_count' => 300,
        ]);

        $story = SuccessStory::query()->create([
            'user_id' => $player->id,
            'full_name' => 'Rising Star',
            'sport' => 'Football',
            'story_text' => 'Big move story',
            'status' => 'approved',
        ]);

        $match = LiveMatch::query()->create([
            'title' => 'Alpha - Beta',
            'league' => 'Super Lig',
            'home_team' => 'Alpha',
            'away_team' => 'Beta',
            'match_date' => now(),
            'is_live' => true,
            'is_finished' => false,
        ]);

        DB::table('featured_content')->insert([
            [
                'featurable_type' => 'success_story',
                'featurable_id' => $story->id,
                'section' => 'homepage',
                'priority' => 50,
                'badge_text' => 'Story',
                'badge_color' => '#111111',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'featurable_type' => 'live_match',
                'featurable_id' => $match->id,
                'section' => 'homepage',
                'priority' => 40,
                'badge_text' => 'Live',
                'badge_color' => '#ff0000',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/featured')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'One cikan icerikler hazir.')
            ->assertJsonPath('data.0.data.type', 'success_story')
            ->assertJsonPath('data.1.data.type', 'live_match');

        $this->getJson('/api/featured/rising-stars')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Yukselen yildizlar hazir.')
            ->assertJsonPath('data.0.name', 'Rising Star');

        $this->getJson('/api/featured/player-of-week')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Rising Star');
    }

    public function test_public_can_get_hot_transfers(): void
    {
        $player = User::factory()->create(['role' => 'player', 'name' => 'Transfer Player']);
        $toClub = User::factory()->create(['role' => 'team', 'name' => 'New Club']);

        PlayerTransfer::query()->create([
            'player_id' => $player->id,
            'to_club_id' => $toClub->id,
            'fee' => 750000,
            'currency' => 'EUR',
            'transfer_date' => now()->subDays(3)->toDateString(),
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/transfer',
            'verification_status' => 'verified',
            'confidence_score' => 0.91,
        ]);

        $this->getJson('/api/featured/hot-transfers')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Sicak transfer listesi hazir.')
            ->assertJsonPath('data.0.player_name', 'Transfer Player')
            ->assertJsonPath('data.0.reliability_score', 91);
    }

    public function test_public_featured_feed_skips_non_public_story_and_unverified_transfer(): void
    {
        $player = User::factory()->create(['role' => 'player', 'name' => 'Hidden Player']);
        $toClub = User::factory()->create(['role' => 'team', 'name' => 'Hidden Club']);

        $pendingStory = SuccessStory::query()->create([
            'user_id' => $player->id,
            'full_name' => 'Hidden Player',
            'sport' => 'Football',
            'story_text' => 'Pending story',
            'status' => 'pending',
        ]);

        $unverifiedTransfer = PlayerTransfer::query()->create([
            'player_id' => $player->id,
            'to_club_id' => $toClub->id,
            'fee' => 500000,
            'currency' => 'EUR',
            'transfer_date' => now()->subDay()->toDateString(),
            'transfer_type' => 'loan',
            'season' => '25/26',
            'window' => 'winter',
            'verification_status' => 'pending',
            'confidence_score' => 0.42,
        ]);

        DB::table('featured_content')->insert([
            [
                'featurable_type' => 'success_story',
                'featurable_id' => $pendingStory->id,
                'section' => 'homepage',
                'priority' => 50,
                'badge_text' => 'Pending',
                'badge_color' => '#111111',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'featurable_type' => 'player_transfer',
                'featurable_id' => $unverifiedTransfer->id,
                'section' => 'homepage',
                'priority' => 40,
                'badge_text' => 'Transfer',
                'badge_color' => '#222222',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/featured')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_authenticated_user_can_manage_featured_content(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'player', 'name' => 'Featured User']);
        Sanctum::actingAs($user, $user->tokenAbilities());

        $this->postJson('/api/featured/admin', [
            'featurable_type' => 'user',
            'featurable_id' => $player->id,
            'section' => 'homepage',
            'priority' => 99,
            'badge_text' => 'Top',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'One cikan icerik kaydedildi.');

        $featuredId = (int) DB::table('featured_content')->value('id');

        $this->getJson('/api/featured/admin?section=homepage')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'One cikan icerik listesi hazir.')
            ->assertJsonPath('data.0.featurable_type', 'user');

        $this->patchJson('/api/featured/admin/'.$featuredId.'/active', [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Durum guncellendi');

        $this->assertDatabaseHas('featured_content', [
            'id' => $featuredId,
            'is_active' => false,
        ]);
    }

    public function test_non_admin_cannot_manage_featured_content(): void
    {
        $user = User::factory()->create(['role' => 'scout']);
        $player = User::factory()->create(['role' => 'player']);

        Sanctum::actingAs($user, $user->tokenAbilities());

        $this->postJson('/api/featured/admin', [
            'featurable_type' => 'user',
            'featurable_id' => $player->id,
            'section' => 'homepage',
        ])
            ->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'forbidden_admin_only');
    }
}
