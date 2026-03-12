<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Opportunity;
use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacyCompatibilityEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_public_legacy_endpoints_return_ok_payloads(): void
    {
        $coach = User::factory()->create([
            'role' => 'coach',
            'name' => 'Coach User',
            'city' => 'Istanbul',
        ]);

        Opportunity::factory()->create([
            'team_user_id' => $coach->id,
            'title' => 'Open Training Day',
            'position' => 'Forward',
            'city' => 'Istanbul',
            'status' => 'open',
        ]);

        User::factory()->create([
            'role' => 'player',
            'confidence_score' => 0.95,
            'views_count' => 120,
        ]);

        $this->getJson('/api/discovery/coach-needs')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/discovery/boosts')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.package_label', 'Sponsorlu');

        $this->getJson('/api/public/players/quality-summary')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1);

        $events = $this->getJson('/api/community-events')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data');

        $eventId = (int) $events->json('data.0.id');

        $this->getJson('/api/community-events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $eventId);

        $this->getJson('/api/success-stories')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_community_event_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/community-events/999999')
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    public function test_authenticated_legacy_routes_require_authentication(): void
    {
        $this->postJson('/api/community-events/1/register')->assertStatus(401);
        $this->postJson('/api/success-stories', [])->assertStatus(401);
        $this->postJson('/api/lawyers/register', [])->assertStatus(401);
        $this->postJson('/api/profile-cards/player/1/like')->assertStatus(401);
    }

    public function test_register_to_community_event_creates_application_and_is_idempotent(): void
    {
        $organizer = User::factory()->create(['role' => 'team']);
        $player = User::factory()->create(['role' => 'player']);
        $opportunity = Opportunity::factory()->create([
            'team_user_id' => $organizer->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($player, ['profile:write']);

        $this->postJson('/api/community-events/'.$opportunity->id.'/register')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('applications', [
            'opportunity_id' => $opportunity->id,
            'player_user_id' => $player->id,
            'status' => 'pending',
        ]);

        $countAfterFirst = Application::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('player_user_id', $player->id)
            ->count();

        $this->postJson('/api/community-events/'.$opportunity->id.'/register')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Basvuru zaten mevcut.');

        $countAfterSecond = Application::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('player_user_id', $player->id)
            ->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_success_story_lawyer_register_and_profile_like_work_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($user, ['profile:write']);

        $this->postJson('/api/success-stories', [
            'full_name' => 'Demo Player',
            'sport' => 'Football',
            'story_text' => 'Signed with a new club after scouting.',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/success-stories')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.full_name', 'Demo Player');

        $this->postJson('/api/lawyers/register', [
            'license_number' => 'TR-12345',
            'specialization' => 'Sports law',
            'years_experience' => 8,
            'hourly_rate' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user_id', $user->id);

        $this->postJson('/api/profile-cards/player/42/like')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.likes', 1);

        $this->postJson('/api/profile-cards/player/42/like')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.likes', 2);
    }

    public function test_success_stories_are_persisted_in_database_when_table_exists(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($user, ['profile:write']);

        $this->postJson('/api/success-stories', [
            'full_name' => 'Database Player',
            'sport' => 'Football',
            'story_text' => 'Permanent story storage works.',
            'old_club' => 'Old Club',
            'new_club' => 'New Club',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('success_stories', [
            'user_id' => $user->id,
            'full_name' => 'Database Player',
            'status' => 'approved',
        ]);

        $story = SuccessStory::query()->firstOrFail();

        $this->getJson('/api/success-stories')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.id', $story->id)
            ->assertJsonPath('data.0.full_name', 'Database Player')
            ->assertJsonPath('data.0.status', 'approved');
    }
}
