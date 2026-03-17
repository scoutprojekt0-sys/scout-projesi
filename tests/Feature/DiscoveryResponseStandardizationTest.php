<?php

namespace Tests\Feature;

use App\Models\BoostPackage;
use App\Models\Opportunity;
use App\Models\PlayerBoost;
use App\Models\Payment;
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

    public function test_manager_needs_endpoint_returns_frontend_compatible_payload(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'name' => 'Manager Demo',
        ]);

        Opportunity::factory()->create([
            'team_user_id' => $manager->id,
            'status' => 'open',
            'title' => 'Kanat Oyuncusu Araniyor',
            'details' => 'Hizli ve teknik oyuncu aranıyor.',
        ]);

        $this->getJson('/api/discovery/manager-needs')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Menajer ihtiyaclari hazir.')
            ->assertJsonPath('data.0.title', 'Kanat Oyuncusu Araniyor')
            ->assertJsonPath('data.0.author_name', 'Manager Demo')
            ->assertJsonPath('data.0.manager_name', 'Manager Demo')
            ->assertJsonPath('data.0.description', 'Hizli ve teknik oyuncu aranıyor.')
            ->assertJsonPath('current_page', 1);
    }

    public function test_weekly_digest_endpoint_returns_frontend_compatible_payload(): void
    {
        User::factory()->create([
            'role' => 'player',
            'name' => 'Top Oyuncu',
            'views_count' => 150,
            'created_at' => now()->subDays(2),
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'name' => 'Manager Week',
        ]);

        $coach = User::factory()->create([
            'role' => 'coach',
            'name' => 'Coach Week',
        ]);

        Opportunity::factory()->create([
            'team_user_id' => $manager->id,
            'status' => 'open',
            'created_at' => now()->subDays(2),
        ]);

        Opportunity::factory()->create([
            'team_user_id' => $coach->id,
            'status' => 'open',
            'created_at' => now()->subDays(3),
        ]);

        $this->getJson('/api/discovery/weekly-digest')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Haftalik bulten hazir.')
            ->assertJsonPath('data.weekly_player_count', 1)
            ->assertJsonPath('data.weekly_manager_needs', 1)
            ->assertJsonPath('data.weekly_coach_needs', 1)
            ->assertJsonPath('data.top_viewed_players.0.name', 'Top Oyuncu')
            ->assertJsonPath('data.top_viewed_players.0.views', 150);
    }

    public function test_discovery_boosts_endpoint_returns_active_boosted_players_only(): void
    {
        $package = BoostPackage::query()->create([
            'name' => 'Haftalik Kesfet',
            'slug' => 'haftalik-kesfet',
            'price' => 399.00,
            'currency' => 'TRY',
            'duration_days' => 7,
            'discover_score' => 20,
            'active' => true,
        ]);

        $activePlayer = User::factory()->create([
            'role' => 'player',
            'name' => 'Aktif Boostlu Oyuncu',
            'position' => 'FW',
            'city' => 'Istanbul',
            'source_url' => 'Hucum ve hiz odakli oyuncu',
        ]);

        $expiredPlayer = User::factory()->create([
            'role' => 'player',
            'name' => 'Suresi Bitmis Oyuncu',
        ]);

        $payment = Payment::query()->create([
            'user_id' => $activePlayer->id,
            'subscription_id' => null,
            'boost_package_id' => $package->id,
            'amount' => $package->price,
            'currency' => 'TRY',
            'payment_method' => 'iyzico',
            'payment_context' => 'boost',
            'transaction_id' => 'discover-boost-1',
            'status' => 'completed',
        ]);

        PlayerBoost::query()->create([
            'user_id' => $activePlayer->id,
            'boost_package_id' => $package->id,
            'payment_id' => $payment->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
            'activated_at' => now()->subHours(2),
        ]);

        PlayerBoost::query()->create([
            'user_id' => $expiredPlayer->id,
            'boost_package_id' => $package->id,
            'payment_id' => null,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'activated_at' => now()->subDays(9),
        ]);

        $this->getJson('/api/discovery/boosts')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Aktif Boostlu Oyuncu')
            ->assertJsonPath('data.0.position', 'FW')
            ->assertJsonPath('data.0.city', 'Istanbul')
            ->assertJsonPath('data.0.summary', 'Hucum ve hiz odakli oyuncu')
            ->assertJsonPath('data.0.package_label', 'Haftalik Kesfet');
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
