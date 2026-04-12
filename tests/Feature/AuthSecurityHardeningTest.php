<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        User::factory()->create([
            'email' => 'lock@test.com',
            'password' => Hash::make('Password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'lock@test.com',
                'password' => 'WrongPassword999',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'lock@test.com',
            'password' => 'WrongPassword999',
        ])->assertStatus(423)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'auth_temporarily_locked')
            ->assertJsonPath('retry_after', fn ($value) => is_int($value) && $value > 0);
    }

    public function test_login_invalid_credentials_returns_consistent_error_code(): void
    {
        User::factory()->create([
            'email' => 'invalid@test.com',
            'password' => Hash::make('Password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'invalid@test.com',
            'password' => 'WrongPassword999',
        ])->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'auth_invalid_credentials');
    }

    public function test_unverified_user_can_login_when_email_verification_is_disabled(): void
    {
        Config::set('app.auth_require_email_verification', false);

        User::factory()->create([
            'email' => 'novalidation@test.com',
            'password' => Hash::make('Password123'),
            'is_verified' => false,
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'novalidation@test.com',
            'password' => 'Password123',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('code', 'auth_logged_in');
    }

    public function test_unverified_user_is_blocked_when_email_verification_is_enabled(): void
    {
        Config::set('app.auth_require_email_verification', true);

        User::factory()->create([
            'email' => 'needsvalidation@test.com',
            'password' => Hash::make('Password123'),
            'is_verified' => false,
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'needsvalidation@test.com',
            'password' => 'Password123',
        ])->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'email_not_verified');
    }

    public function test_role_change_revokes_existing_tokens(): void
    {
        $user = User::factory()->create(['role' => 'team']);
        $user->createToken('token-1', ['team']);
        $user->createToken('token-2', ['team']);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $user->update(['role' => 'scout']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_security_command_revokes_only_legacy_wildcard_tokens(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $user->createToken('legacy-token');
        $user->createToken('new-token', $user->tokenAbilities());

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->artisan('security:revoke-legacy-tokens')->assertExitCode(0);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $remainingAbilities = DB::table('personal_access_tokens')->value('abilities');
        $abilities = json_decode((string) $remainingAbilities, true);

        $this->assertIsArray($abilities);
        $this->assertFalse(in_array('*', $abilities, true));
    }

    public function test_login_returns_expiring_token_and_persists_expires_at(): void
    {
        Config::set('sanctum.expiration', 60);

        User::factory()->create([
            'email' => 'expires@test.com',
            'password' => Hash::make('Password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'expires@test.com',
            'password' => 'Password123',
        ])->assertOk();

        $response->assertJsonPath('code', 'auth_logged_in');
        $response->assertJsonPath('data.expires_at', fn ($value) => is_string($value) && $value !== '');

        $latestExpiresAt = DB::table('personal_access_tokens')->max('expires_at');
        $this->assertNotNull($latestExpiresAt);
    }

    public function test_sessions_endpoint_lists_user_tokens_and_marks_current(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $tokenOne = $user->createToken('device-1', $user->tokenAbilities())->plainTextToken;
        $user->createToken('device-2', $user->tokenAbilities());
        $currentTokenId = (int) explode('|', $tokenOne)[0];

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$tokenOne)
            ->getJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $sessions = $response->json('data');
        $this->assertCount(2, $sessions);
        $this->assertTrue(collect($sessions)->contains(fn ($s) => (int) $s['id'] === $currentTokenId && $s['is_current'] === true));
        $this->assertTrue(collect($sessions)->contains(fn ($s) => array_key_exists('device_label', $s)));
        $this->assertFalse(collect($sessions)->contains(fn ($s) => array_key_exists('ip_address', $s)));
        $this->assertFalse(collect($sessions)->contains(fn ($s) => array_key_exists('user_agent', $s)));
    }

    public function test_revoke_session_deletes_target_token(): void
    {
        $user = User::factory()->create(['role' => 'team']);
        $current = $user->createToken('current', $user->tokenAbilities())->plainTextToken;
        $other = $user->createToken('other', $user->tokenAbilities())->plainTextToken;
        $otherId = (int) explode('|', $other)[0];

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->deleteJson('/api/auth/sessions/'.$otherId)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherId]);
        $this->assertDatabaseHas('audit_events', [
            'user_id' => $user->id,
            'event_type' => 'auth.session.revoke_one',
        ]);
    }

    public function test_revoke_session_rejects_current_token_id(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $current = $user->createToken('current', $user->tokenAbilities())->plainTextToken;
        $currentId = (int) explode('|', $current)[0];

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->deleteJson('/api/auth/sessions/'.$currentId)
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'session_revoke_current_not_allowed');
    }

    public function test_logout_all_revokes_other_tokens_only(): void
    {
        $user = User::factory()->create(['role' => 'coach']);
        $current = $user->createToken('current', $user->tokenAbilities())->plainTextToken;
        $otherOne = $user->createToken('other-1', $user->tokenAbilities())->plainTextToken;
        $otherTwo = $user->createToken('other-2', $user->tokenAbilities())->plainTextToken;
        $currentId = (int) explode('|', $current)[0];
        $otherOneId = (int) explode('|', $otherOne)[0];
        $otherTwoId = (int) explode('|', $otherTwo)[0];

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->deleteJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.revoked_count', 2);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentId]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherOneId]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherTwoId]);
        $this->assertDatabaseHas('audit_events', [
            'user_id' => $user->id,
            'event_type' => 'auth.session.revoke_others',
        ]);
    }

    public function test_refresh_rotates_current_token(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $current = $user->createToken('current', $user->tokenAbilities())->plainTextToken;
        $currentId = (int) explode('|', $current)[0];

        $response = $this->withHeader('Authorization', 'Bearer '.$current)
            ->postJson('/api/auth/refresh')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $newToken = (string) $response->json('data.token');
        $newTokenId = (int) explode('|', $newToken)[0];

        $this->assertNotEquals($currentId, $newTokenId);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentId]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $newTokenId]);
    }

    public function test_unauthenticated_requests_cannot_access_session_management_endpoints(): void
    {
        $this->getJson('/api/auth/sessions')->assertStatus(401);
        $this->deleteJson('/api/auth/sessions')->assertStatus(401);
        $this->deleteJson('/api/auth/sessions/1')->assertStatus(401);
        $this->postJson('/api/auth/refresh')->assertStatus(401);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $attacker = User::factory()->create(['role' => 'player']);
        $victim = User::factory()->create(['role' => 'team']);

        $attackerToken = $attacker->createToken('attacker', $attacker->tokenAbilities())->plainTextToken;
        $victimToken = $victim->createToken('victim', $victim->tokenAbilities())->plainTextToken;
        $victimTokenId = (int) explode('|', $victimToken)[0];

        $this->withHeader('Authorization', 'Bearer '.$attackerToken)
            ->deleteJson('/api/auth/sessions/'.$victimTokenId)
            ->assertStatus(404)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'session_not_found');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $victimTokenId]);
    }

    public function test_refresh_invalidates_old_token_for_subsequent_requests(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $oldToken = $user->createToken('current', $user->tokenAbilities())->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->postJson('/api/auth/refresh')
            ->assertOk();

        $newToken = (string) $response->json('data.token');
        $this->assertNull(PersonalAccessToken::findToken($oldToken));
        $this->assertNotNull(PersonalAccessToken::findToken($newToken));

        $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
