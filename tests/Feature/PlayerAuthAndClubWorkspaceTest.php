<?php

namespace Tests\Feature;

use App\Models\ClubInternalPlayer;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerAuthAndClubWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_set_first_password_and_then_login_with_team_and_player_name(): void
    {
        $player = User::factory()->create([
            'role' => 'player',
            'name' => 'Ahmet Yilmaz',
            'email' => 'ahmet@example.com',
            'password' => Hash::make('temporary-secret'),
            'player_password_initialized' => false,
        ]);

        PlayerProfile::query()->create([
            'user_id' => $player->id,
            'current_team' => 'Besiktas U19',
            'position' => 'Forvet',
        ]);

        $this->postJson('/api/auth/player/login', [
            'team_name' => 'Besiktas U19',
            'player_name' => 'Ahmet Yilmaz',
            'password' => 'Secret123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'auth_invalid_credentials');

        $this->postJson('/api/auth/player/set-password', [
            'team_name' => 'Besiktas U19',
            'player_name' => 'Ahmet Yilmaz',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('code', 'player_password_initialized')
            ->assertJsonPath('data.user.id', $player->id);

        $this->assertTrue((bool) $player->fresh()->player_password_initialized);

        $this->postJson('/api/auth/player/login', [
            'team_name' => 'Besiktas U19',
            'player_name' => 'Ahmet Yilmaz',
            'password' => 'Secret123',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('code', 'player_logged_in')
            ->assertJsonPath('data.user.id', $player->id);
    }

    public function test_club_can_create_player_account_and_reopen_first_password_setup(): void
    {
        $club = User::factory()->club()->create([
            'name' => 'Besiktas',
            'email' => 'club@example.com',
        ]);

        DB::table('team_profiles')->insert([
            'user_id' => $club->id,
            'team_name' => 'Besiktas U19',
            'league_level' => 'Akademi',
            'city' => 'Istanbul',
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($club, ['profile:read', 'profile:write']);

        $createResponse = $this->postJson('/api/club/internal-players', [
            'group' => 'u19',
            'status' => 'active',
            'name' => 'Dogu Kaniyolu',
            'sport' => 'futbol',
            'position' => 'Forvet',
            'birthYear' => '2008',
            'height' => '180',
            'dominantFoot' => 'sag',
            'bio' => 'Hizli forvet',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Dogu Kaniyolu');

        $internalPlayerId = (int) $createResponse->json('data.id');

        $accountResponse = $this->postJson("/api/club/internal-players/{$internalPlayerId}/account")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.account_enabled', true)
            ->assertJsonPath('data.team_name', 'Besiktas U19')
            ->assertJsonPath('data.player_name', 'Dogu Kaniyolu')
            ->assertJsonPath('data.player_password_initialized', false);

        $playerUserId = (int) $accountResponse->json('data.player_user_id');
        $playerUser = User::query()->findOrFail($playerUserId);

        $this->assertSame('player', $playerUser->role);
        $this->assertFalse((bool) $playerUser->player_password_initialized);

        $playerProfile = PlayerProfile::query()->findOrFail($playerUserId);
        $this->assertSame('Besiktas U19', $playerProfile->current_team);
        $this->assertSame('Forvet', $playerProfile->position);

        $playerUser->forceFill([
            'player_password_initialized' => true,
            'password' => Hash::make('Another123'),
        ])->save();

        $this->postJson("/api/club/internal-players/{$internalPlayerId}/account/reset-password-setup")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.account_enabled', true)
            ->assertJsonPath('data.player_password_initialized', false);

        $this->assertFalse((bool) $playerUser->fresh()->player_password_initialized);

        $internalPlayer = ClubInternalPlayer::query()->findOrFail($internalPlayerId);
        $this->assertSame($club->id, $internalPlayer->club_user_id);
    }
}
