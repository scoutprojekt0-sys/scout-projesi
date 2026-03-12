<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryResponseStandardizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_players_endpoint_returns_standard_paginated_payload(): void
    {
        User::factory()->create([
            'role' => 'player',
            'name' => 'Public Player',
        ]);

        $this->getJson('/api/public/players')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Public oyuncu listesi hazir.')
            ->assertJsonPath('data.0.name', 'Public Player')
            ->assertJsonPath('current_page', 1);
    }

    public function test_club_needs_endpoint_returns_standard_paginated_payload(): void
    {
        $team = User::factory()->create([
            'role' => 'team',
            'name' => 'Kulup A',
        ]);

        Opportunity::factory()->create([
            'team_user_id' => $team->id,
            'status' => 'open',
            'title' => 'Forvet Araniyor',
        ]);

        $this->getJson('/api/club-needs')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Kulup ihtiyaclari hazir.')
            ->assertJsonPath('data.0.title', 'Forvet Araniyor')
            ->assertJsonPath('current_page', 1);
    }

    public function test_player_of_week_endpoint_returns_standard_success_payload(): void
    {
        User::factory()->create([
            'role' => 'player',
            'name' => 'Haftanin Oyuncusu',
            'rating' => 9.1,
            'created_at' => now()->subDays(2),
        ]);

        $this->getJson('/api/featured/player-of-week')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Haftanin oyuncusu hazir.')
            ->assertJsonPath('data.name', 'Haftanin Oyuncusu');
    }
}
