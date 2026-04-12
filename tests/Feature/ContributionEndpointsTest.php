<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContributionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_and_list_own_contributions_without_email_leak(): void
    {
        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:read', 'profile:write']);

        $create = $this->postJson('/api/contributions', [
            'model_type' => 'PlayerProfile',
            'contribution_type' => 'correction',
            'description' => 'Oyuncunun pozisyonu ve kulup bilgisi guncellenmeli, mevcut veri eski kalmis gorunuyor.',
            'source_url' => 'https://example.com/profile-update',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.model_type', 'PlayerProfile')
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonMissingPath('data.reviewer.email');

        $this->getJson('/api/contributions/my')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.model_type', 'PlayerProfile')
            ->assertJsonMissingPath('data.0.user.email')
            ->assertJsonMissingPath('data.0.reviewer.email');
    }

    public function test_reviewer_can_view_contribution_detail_without_email_fields(): void
    {
        $contributor = User::factory()->create(['role' => 'player']);
        $reviewer = User::factory()->create(['role' => 'scout', 'editor_role' => 'reviewer']);

        $contribution = UserContribution::query()->create([
            'user_id' => $contributor->id,
            'model_type' => 'PlayerTransfer',
            'model_id' => 10,
            'contribution_type' => 'add_source',
            'description' => 'Transfer icin yeni bir guvenilir kaynak eklendi ve dogrulanmali.',
            'status' => 'pending',
            'quality_score' => 0.8,
        ]);

        Sanctum::actingAs($reviewer, ['profile:read', 'staff']);

        $this->getJson('/api/contributions/'.$contribution->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.id', $contribution->id)
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonMissingPath('data.reviewer.email');
    }
}
