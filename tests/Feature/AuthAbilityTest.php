<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAbilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_create_opportunity(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['player']);

        $response = $this->postJson('/api/opportunities', [
            'title' => 'Test Opportunity',
        ]);

        $response->assertStatus(403);
    }

    public function test_team_can_access_opportunity_create_route_with_opportunity_write_ability(): void
    {
        $user = User::factory()->create(['role' => 'team']);
        Sanctum::actingAs($user, ['opportunity:write']);

        $response = $this->postJson('/api/opportunities', []);

        $response->assertStatus(422);
    }

    public function test_manager_can_create_opportunity_with_opportunity_write_ability(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($user, ['opportunity:write']);

        $response = $this->postJson('/api/opportunities', [
            'title' => 'Scout gereken oyuncu',
            'position' => 'Forward',
            'city' => 'Istanbul',
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.team_user_id', $user->id);
    }

    public function test_coach_can_create_opportunity_with_opportunity_write_ability(): void
    {
        $user = User::factory()->create(['role' => 'coach']);
        Sanctum::actingAs($user, ['opportunity:write']);

        $response = $this->postJson('/api/opportunities', [
            'title' => 'Antrenman grubu icin guard araniyor',
            'position' => 'Guard',
            'city' => 'Istanbul',
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.team_user_id', $user->id);
    }

    public function test_opportunities_index_can_filter_by_sport_using_team_alias(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'sport' => 'basketball',
        ]);
        Sanctum::actingAs($manager, ['opportunity:write']);

        DB::table('opportunities')->insert([
            'team_user_id' => $manager->id,
            'title' => 'Basketbol guard ihtiyaci',
            'position' => 'Guard',
            'city' => 'Istanbul',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/opportunities?sport=basketball')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.team_user_id', $manager->id);
    }

    public function test_manager_can_view_incoming_applications_with_application_incoming_ability(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $player = User::factory()->create(['role' => 'player']);

        $opportunityId = DB::table('opportunities')->insertGetId([
            'team_user_id' => $manager->id,
            'title' => 'Menajer ihtiyaci',
            'position' => 'Midfielder',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('applications')->insert([
            'opportunity_id' => $opportunityId,
            'player_user_id' => $player->id,
            'message' => 'Ilgileniyorum',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($manager, ['application:incoming']);

        $this->getJson('/api/applications/incoming')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.player_id', $player->id)
            ->assertJsonPath('data.data.0.opportunity_id', $opportunityId);
    }

    public function test_user_without_contact_write_ability_cannot_send_contact(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['player']);

        $response = $this->postJson('/api/contacts', [
            'to_user_id' => 2,
            'message' => 'hello',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_without_media_read_ability_cannot_list_media(): void
    {
        $user = User::factory()->create(['role' => 'team']);
        Sanctum::actingAs($user, ['team']);

        $response = $this->getJson('/api/users/1/media');

        $response->assertStatus(403);
    }

    public function test_legacy_wildcard_token_is_revoked_and_forced_to_relogin(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $token = $user->createToken('legacy-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_auth_me_returns_null_for_missing_local_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'player',
            'photo_url' => 'profile-photos/missing-auth-photo.jpg',
        ]);

        Sanctum::actingAs($user, $user->tokenAbilities());

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.photo_url', null);
    }
}
