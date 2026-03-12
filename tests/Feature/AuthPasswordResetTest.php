<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_success_message_for_existing_user(): void
    {
        User::factory()->create(['email' => 'reset@test.com']);

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'reset@test.com',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('code', 'password_reset_link_sent');
    }

    public function test_forgot_password_returns_generic_success_for_unknown_user(): void
    {
        $this->postJson('/api/auth/password/forgot', [
            'email' => 'notfound@test.com',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('code', 'password_reset_link_sent');
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'resetok@test.com',
            'password' => Hash::make('OldPassword123'),
        ]);
        $user->createToken('device-1', $user->tokenAbilities());
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'resetok@test.com',
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('code', 'password_reset_success');

        $this->assertTrue(Hash::check('NewPassword123', (string) $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'invalidtoken@test.com']);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'invalidtoken@test.com',
            'token' => 'invalid-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'password_reset_token_invalid');
    }
}
