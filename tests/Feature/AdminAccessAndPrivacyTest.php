<?php

namespace Tests\Feature;

use App\Models\SocialMediaAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccessAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_users_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/users')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_admin_only');
    }

    public function test_admin_can_access_admin_users_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['profile:read']);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Kullanici listesi hazir.');
    }

    public function test_admin_profile_card_response_is_standardized(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'player']);

        Sanctum::actingAs($admin, ['profile:read']);

        $this->getJson('/api/users/'.$target->id.'/profile-card')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Profil karti hazir.')
            ->assertJsonPath('data.user.id', $target->id);
    }

    public function test_player_email_and_phone_are_hidden_from_other_users(): void
    {
        $owner = User::factory()->create([
            'role' => 'player',
            'email' => 'owner.player@test.com',
            'phone' => '5551230000',
        ]);

        $viewer = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($viewer, ['profile:read']);

        $response = $this->getJson('/api/players/'.$owner->id)
            ->assertOk();

        $this->assertNull($response->json('data.phone'));
        $this->assertStringContainsString('***@', (string) $response->json('data.email'));
    }

    public function test_player_sees_own_email_and_phone(): void
    {
        $owner = User::factory()->create([
            'role' => 'player',
            'email' => 'self.player@test.com',
            'phone' => '5557770000',
        ]);

        Sanctum::actingAs($owner, ['profile:read']);

        $this->getJson('/api/players/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'self.player@test.com')
            ->assertJsonPath('data.phone', '5557770000');
    }

    public function test_social_media_is_visible_only_to_owner_or_admin(): void
    {
        $owner = User::factory()->create(['role' => 'player']);
        $outsider = User::factory()->create(['role' => 'player']);
        $admin = User::factory()->create(['role' => 'admin']);

        SocialMediaAccount::create([
            'user_id' => $owner->id,
            'platform' => 'instagram',
            'username' => 'owner_ig',
            'url' => 'https://instagram.com/owner_ig',
            'follower_count' => 1200,
            'verified' => false,
        ]);

        Sanctum::actingAs($outsider, ['profile:read']);
        $this->getJson('/api/users/'.$owner->id.'/social-media')
            ->assertStatus(403);

        Sanctum::actingAs($owner, ['profile:read']);
        $this->getJson('/api/users/'.$owner->id.'/social-media')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'owner_ig');

        Sanctum::actingAs($admin, ['profile:read']);
        $this->getJson('/api/users/'.$owner->id.'/social-media')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'owner_ig');
    }

    public function test_admin_rate_limit_summary_requires_admin_and_returns_standard_payload(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($nonAdmin, ['profile:read']);

        $this->getJson('/api/admin/ops/rate-limit-summary')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_admin_only');

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['profile:read']);

        $this->getJson('/api/admin/ops/rate-limit-summary')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Rate limit ozeti hazir.')
            ->assertJsonPath('data.rate_limit_per_minute', fn ($value) => is_int($value) && $value > 0)
            ->assertJsonPath('data.requests_total_today', fn ($value) => is_int($value) && $value >= 0);
    }

    public function test_non_admin_cannot_manage_boost_packages(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/admin/billing/boost-packages')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_admin_only');
    }

    public function test_admin_can_list_and_update_boost_packages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['profile:read']);

        $listResponse = $this->getJson('/api/admin/billing/boost-packages')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $packageId = $listResponse->json('data.0.id');
        $this->assertNotNull($packageId);

        $this->putJson('/api/admin/billing/boost-packages/'.$packageId, [
            'name' => 'Gunluk Kesfet Plus',
            'description' => '1 gun vitrin',
            'price' => 219.90,
            'currency' => 'TRY',
            'duration_days' => 1,
            'discover_score' => 15,
            'active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Gunluk Kesfet Plus')
            ->assertJsonPath('data.price', '219.90');
    }
}
