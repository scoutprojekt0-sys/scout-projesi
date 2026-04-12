<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemControllerPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_profile_card_does_not_expose_latest_user_agent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'player']);

        DB::table('player_profiles')->insert([
            'user_id' => $player->id,
            'birth_year' => now()->year - 20,
            'position' => 'FW',
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->getJson('/api/users/'.$player->id.'/profile-card')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('data.latest_user_agent');
    }
}
