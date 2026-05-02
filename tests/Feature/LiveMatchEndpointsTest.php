<?php

namespace Tests\Feature;

use App\Models\ClubTeamGroup;
use App\Models\ClubInternalPlayer;
use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveMatchEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_live_matches_and_count(): void
    {
        LiveMatch::query()->create([
            'title' => 'Galatasaray - Fenerbahce',
            'league' => 'Super Lig',
            'home_team' => 'Galatasaray',
            'away_team' => 'Fenerbahce',
            'home_score' => 1,
            'away_score' => 0,
            'match_date' => now(),
            'is_live' => true,
            'is_finished' => false,
        ]);

        $this->getJson('/api/live-matches')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.league', 'Super Lig');

        $this->getJson('/api/live-matches/count')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.count', 1);
    }

    public function test_public_can_get_recent_and_upcoming_matches(): void
    {
        LiveMatch::query()->create([
            'title' => 'Finished Match',
            'league' => 'Super Lig',
            'home_team' => 'A',
            'away_team' => 'B',
            'home_score' => 2,
            'away_score' => 1,
            'match_date' => now()->subDay(),
            'is_live' => false,
            'is_finished' => true,
        ]);

        LiveMatch::query()->create([
            'title' => 'Upcoming Match',
            'league' => '1. Lig',
            'home_team' => 'C',
            'away_team' => 'D',
            'match_date' => now()->addDay(),
            'is_live' => false,
            'is_finished' => false,
        ]);

        $this->getJson('/api/matches/recent')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.status', 'finished');

        $this->getJson('/api/matches/upcoming')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.status', 'scheduled');
    }

    public function test_public_can_get_match_details_and_alias_routes(): void
    {
        $match = LiveMatch::query()->create([
            'title' => 'Besiktas - Trabzonspor',
            'league' => 'Super Lig',
            'home_team' => 'Besiktas',
            'away_team' => 'Trabzonspor',
            'home_score' => 2,
            'away_score' => 0,
            'match_date' => now(),
            'is_live' => true,
            'is_finished' => false,
            'round' => 'meta::{"round":null,"meta":{"location":"Tupras Stadium","sport":"football","stream_url":"https://example.com/live"}}',
        ]);

        $this->getJson('/api/match-center/live-matches')
            ->assertOk()
            ->assertJsonPath('data.0.location', 'Tupras Stadium')
            ->assertJsonPath('data.0.sport', 'football')
            ->assertJsonMissingPath('data.0.source_user_id');

        $this->getJson('/api/match/'.$match->id.'/details')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.stadium', 'Tupras Stadium')
            ->assertJsonPath('data.stream_url', 'https://example.com/live')
            ->assertJsonMissingPath('data.source_user_id');

        $this->getJson('/api/match/'.$match->id.'/scorers')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(3, 'data.scorers');
    }

    public function test_authenticated_user_can_store_live_match_and_send_update(): void
    {
        $user = User::factory()->create(['role' => 'scout', 'name' => 'Scout One']);
        Sanctum::actingAs($user, ['profile:write']);

        $this->postJson('/api/live-matches', [
            'match_name' => 'Galatasaray - Kasimpasa',
            'league' => 'Super Lig',
            'location' => 'RAMS Park',
            'sport' => 'football',
            'stream_url' => 'https://example.com/stream',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.home_team', 'Galatasaray')
            ->assertJsonPath('data.away_team', 'Kasimpasa');

        $match = LiveMatch::query()->latest('id')->firstOrFail();

        $this->assertStringContainsString('meta::', (string) $match->round);

        $this->postJson('/api/match/'.$match->id.'/live-update', [
            'minute' => 42,
            'event' => 'goal',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.match_id', $match->id)
            ->assertJsonPath('data.payload.minute', 42);
    }

    public function test_club_can_start_private_live_match_session(): void
    {
        $user = User::factory()->create(['role' => 'club', 'name' => 'Test Club']);
        ClubTeamGroup::query()->create([
            'club_user_id' => $user->id,
            'group_key' => 'u18-a',
            'name' => 'U18 A Takimi',
            'is_showcased' => false,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($user, ['profile:write']);

        $this->postJson('/api/club/live-matches/start', [
            'group_key' => 'u18-a',
            'opponent' => 'Rakip SK',
            'match_date' => now()->toIso8601String(),
            'periods' => 4,
            'sport' => 'basketball',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.group_key', 'u18-a')
            ->assertJsonPath('data.group_name', 'U18 A Takimi')
            ->assertJsonPath('data.opponent', 'Rakip SK')
            ->assertJsonPath('data.periods', 4)
            ->assertJsonPath('data.status', 'live');

        $this->assertDatabaseHas('live_matches', [
            'home_team' => 'Test Club',
            'away_team' => 'Rakip SK',
            'visibility' => 'club_private',
            'group_key' => 'u18-a',
            'periods' => 4,
            'is_live' => true,
            'is_finished' => false,
            'club_user_id' => $user->id,
        ]);
    }

    public function test_club_can_store_football_live_match_event(): void
    {
        $user = User::factory()->create(['role' => 'club', 'name' => 'Football Club']);
        ClubTeamGroup::query()->create([
            'club_user_id' => $user->id,
            'group_key' => 'u16-football',
            'name' => 'U16 Futbol',
            'is_showcased' => false,
            'sort_order' => 1,
        ]);
        $player = ClubInternalPlayer::query()->create([
            'club_user_id' => $user->id,
            'group_key' => 'u16-football',
            'name' => 'Ahmet Yilmaz',
            'sport' => 'football',
            'shirt_number' => '9',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['profile:write']);

        $startResponse = $this->postJson('/api/club/live-matches/start', [
            'group_key' => 'u16-football',
            'opponent' => 'Rakip FK',
            'match_date' => now()->toIso8601String(),
            'periods' => 2,
            'sport' => 'football',
        ])->assertCreated();

        $matchId = $startResponse->json('data.id');

        $this->postJson('/api/club/live-matches/'.$matchId.'/events', [
            'player_id' => $player->id,
            'event_type' => 'Gol',
            'period' => 1,
            'minute' => 12,
            'second' => 8,
            'x' => 0.82,
            'y' => 0.47,
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.event_type', 'Gol')
            ->assertJsonPath('data.player_id', $player->id);
    }

    public function test_club_can_store_volleyball_live_match_event(): void
    {
        $user = User::factory()->create(['role' => 'club', 'name' => 'Volley Club']);
        ClubTeamGroup::query()->create([
            'club_user_id' => $user->id,
            'group_key' => 'u18-volley',
            'name' => 'U18 Voleybol',
            'is_showcased' => false,
            'sort_order' => 1,
        ]);
        $player = ClubInternalPlayer::query()->create([
            'club_user_id' => $user->id,
            'group_key' => 'u18-volley',
            'name' => 'Zeynep Kaya',
            'sport' => 'volleyball',
            'shirt_number' => '7',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['profile:write']);

        $startResponse = $this->postJson('/api/club/live-matches/start', [
            'group_key' => 'u18-volley',
            'opponent' => 'Rakip VK',
            'match_date' => now()->toIso8601String(),
            'periods' => 3,
            'sport' => 'volleyball',
        ])->assertCreated();

        $matchId = $startResponse->json('data.id');

        $this->postJson('/api/club/live-matches/'.$matchId.'/events', [
            'player_id' => $player->id,
            'event_type' => 'Sayi',
            'period' => 1,
            'minute' => 4,
            'second' => 11,
            'x' => 0.58,
            'y' => 0.31,
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.event_type', 'Sayi')
            ->assertJsonPath('data.player_id', $player->id);
    }

    public function test_player_schedule_for_today_creates_live_match_notification(): void
    {
        $user = User::factory()->create(['role' => 'player', 'name' => 'Player One']);
        Sanctum::actingAs($user, ['player']);

        $this->postJson('/api/player/match-schedules', [
            'match_title' => 'Bugunku Mac',
            'team_name' => 'Ev Sahibi',
            'opponent_name' => 'Rakip',
            'position' => '10 numara',
            'match_date' => now()->toIso8601String(),
            'city' => 'Izmir',
            'venue' => 'Stadyum',
            'notes' => 'Oyuncu takvim kaydi',
        ])->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('live_matches', [
            'title' => 'Bugunku Mac',
            'home_team' => 'Ev Sahibi',
            'away_team' => 'Rakip',
            'is_live' => true,
            'is_finished' => false,
        ]);

        $this->getJson('/api/live-matches')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.source_role', 'player')
            ->assertJsonPath('data.0.source_name', 'Player One');
    }

    public function test_player_schedule_for_future_does_not_create_live_match_notification(): void
    {
        $user = User::factory()->create(['role' => 'player', 'name' => 'Player Future']);
        Sanctum::actingAs($user, ['player']);

        $this->postJson('/api/player/match-schedules', [
            'match_title' => 'Gelecek Mac',
            'team_name' => 'Ev Sahibi',
            'opponent_name' => 'Rakip',
            'position' => 'Kanat',
            'match_date' => now()->addDays(10)->toIso8601String(),
            'city' => 'Istanbul',
            'venue' => 'Saha',
        ])->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseCount('live_matches', 0);
    }

    public function test_past_live_match_is_automatically_hidden_after_match_time_passes(): void
    {
        LiveMatch::query()->create([
            'title' => 'Bitmis Mac',
            'league' => 'Super Lig',
            'home_team' => 'A',
            'away_team' => 'B',
            'match_date' => now()->subMinute(),
            'is_live' => true,
            'is_finished' => false,
        ]);

        $this->getJson('/api/live-matches')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->assertDatabaseHas('live_matches', [
            'title' => 'Bitmis Mac',
            'is_live' => false,
            'is_finished' => true,
        ]);
    }
}
