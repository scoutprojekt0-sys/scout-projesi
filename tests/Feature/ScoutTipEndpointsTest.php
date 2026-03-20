<?php

namespace Tests\Feature;

use App\Models\ScoutTip;
use App\Models\ModerationQueue;
use App\Models\PlayerTransfer;
use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScoutTipEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_tip_and_see_scoreboard(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        $clip = VideoClip::create([
            'user_id' => $user->id,
            'title' => 'Mahalle Maci',
            'video_url' => 'https://example.com/video-1',
            'platform' => 'custom',
        ]);

        Sanctum::actingAs($user, ['profile:read', 'profile:write']);

        $create = $this->postJson('/api/scout-tips', [
            'video_clip_id' => $clip->id,
            'source_type' => 'new_player',
            'player_name' => 'Ahmet Yilmaz',
            'birth_year' => 2010,
            'position' => 'winger',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
            'team_name' => 'Moda Genclik',
            'competition_level' => 'mahalle',
            'guardian_consent_status' => 'received',
            'description' => 'Hizli karar veren, bire birde adam eksilten ve oyuna surekli etki eden bir oyuncu.',
        ]);

        $create
            ->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.player_name', 'Ahmet Yilmaz')
            ->assertJsonPath('data.submitted_by', $user->id);

        $this->getJson('/api/scout-tips/my')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.player_name', 'Ahmet Yilmaz');

        $this->getJson('/api/scout-scoreboard/me')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.scout_tips_count', 1);
    }

    public function test_reviewer_can_screen_and_shortlist_tip(): void
    {
        $submitter = User::factory()->create(['role' => 'player']);
        $reviewer = User::factory()->create(['role' => 'scout', 'editor_role' => 'reviewer']);

        $tip = ScoutTip::create([
            'submitted_by' => $submitter->id,
            'source_type' => 'new_player',
            'player_name' => 'Mehmet Kaya',
            'city' => 'Ankara',
            'guardian_consent_status' => 'received',
            'description' => 'Fiziksel olarak kuvvetli ve baski altinda dogru karar veriyor.',
            'ai_quality_score' => 50,
            'final_score' => 50,
        ]);

        Sanctum::actingAs($reviewer, ['profile:read', 'profile:write', 'staff']);

        $this->postJson('/api/scout-tips/'.$tip->id.'/screen', [
            'review_score' => 80,
            'notes' => 'Klip yeterli kaliteye sahip.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'screened');

        $this->postJson('/api/scout-tips/'.$tip->id.'/shortlist', [
            'notes' => 'Bolgesel scout ekibine aktarildi.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shortlisted');

        $submitter->refresh();
        $this->assertGreaterThan(0, $submitter->scout_points);
    }

    public function test_non_owner_cannot_view_someone_else_tip(): void
    {
        $owner = User::factory()->create(['role' => 'player']);
        $other = User::factory()->create(['role' => 'player']);
        $tip = ScoutTip::create([
            'submitted_by' => $owner->id,
            'source_type' => 'new_player',
            'player_name' => 'Can Demir',
            'city' => 'Izmir',
            'guardian_consent_status' => 'pending',
            'description' => 'Teknik kapasitesi yuksek bir oyuncu.',
            'ai_quality_score' => 40,
            'final_score' => 40,
        ]);

        Sanctum::actingAs($other, ['profile:read']);

        $this->getJson('/api/scout-tips/'.$tip->id)->assertStatus(403);
    }

    public function test_transfer_creation_attaches_reward_candidate_for_attributed_tip(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $clubFrom = User::factory()->create(['role' => 'team']);
        $clubTo = User::factory()->create(['role' => 'team']);
        $submitter = User::factory()->create(['role' => 'player']);
        $manager = User::factory()->create(['role' => 'manager']);

        ScoutTip::create([
            'submitted_by' => $submitter->id,
            'player_id' => $player->id,
            'source_type' => 'existing_player',
            'player_name' => $player->name,
            'city' => 'Bursa',
            'guardian_consent_status' => 'received',
            'description' => 'Profesyonel seviyeye çıkma potansiyeli yüksek.',
            'status' => 'signed',
            'ai_quality_score' => 70,
            'final_score' => 85,
        ]);

        Sanctum::actingAs($manager, ['profile:read', 'profile:write']);

        $response = $this->postJson('/api/transfers', [
            'player_id' => $player->id,
            'from_club_id' => $clubFrom->id,
            'to_club_id' => $clubTo->id,
            'fee' => 100000,
            'currency' => 'EUR',
            'transfer_date' => '2026-03-20',
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/transfer',
        ]);

        $response->assertStatus(201)->assertJsonPath('ok', true);

        $this->assertDatabaseHas('scout_rewards', [
            'user_id' => $submitter->id,
            'basis' => 'verified_transfer',
            'reward_type' => 'commission_share',
        ]);
    }

    public function test_moderation_approval_verifies_transfer_and_approves_reward(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $clubFrom = User::factory()->create(['role' => 'team']);
        $clubTo = User::factory()->create(['role' => 'team']);
        $submitter = User::factory()->create(['role' => 'player']);
        $admin = User::factory()->create(['role' => 'manager', 'editor_role' => 'admin']);

        ScoutTip::create([
            'submitted_by' => $submitter->id,
            'player_id' => $player->id,
            'source_type' => 'existing_player',
            'player_name' => $player->name,
            'city' => 'Istanbul',
            'guardian_consent_status' => 'received',
            'description' => 'Transfer potansiyeli yuksek oyuncu.',
            'status' => 'signed',
            'ai_quality_score' => 75,
            'final_score' => 90,
        ]);

        $transfer = PlayerTransfer::create([
            'player_id' => $player->id,
            'from_club_id' => $clubFrom->id,
            'to_club_id' => $clubTo->id,
            'fee' => 200000,
            'currency' => 'EUR',
            'transfer_date' => '2026-03-20',
            'transfer_type' => 'permanent',
            'season' => '25/26',
            'window' => 'summer',
            'source_url' => 'https://example.com/transfer-verified',
            'verification_status' => 'pending',
            'confidence_score' => 0.7,
            'created_by' => $admin->id,
        ]);

        ModerationQueue::create([
            'model_type' => 'PlayerTransfer',
            'model_id' => $transfer->id,
            'status' => 'pending',
            'priority' => 'medium',
            'reason' => 'new_entry',
            'submitted_by' => $admin->id,
            'confidence_score' => 0.7,
        ]);

        app(\App\Services\ScoutAttributionService::class)->attachTransferRewardCandidate($transfer);

        $queueId = ModerationQueue::query()
            ->where('model_type', 'PlayerTransfer')
            ->where('model_id', $transfer->id)
            ->value('id');

        Sanctum::actingAs($admin, ['profile:read', 'profile:write', 'staff']);

        $this->postJson('/api/moderation/'.$queueId.'/approve', [
            'notes' => 'Transfer dogrulandi.',
        ])->assertOk();

        $this->assertDatabaseHas('player_transfers', [
            'id' => $transfer->id,
            'verification_status' => 'verified',
        ]);

        $this->assertDatabaseHas('scout_rewards', [
            'user_id' => $submitter->id,
            'basis' => 'verified_transfer',
            'status' => 'approved',
        ]);
    }
}
