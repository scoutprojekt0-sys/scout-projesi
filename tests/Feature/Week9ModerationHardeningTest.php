<?php

namespace Tests\Feature;

use App\Models\ModerationQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Week9ModerationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_reviewer_cannot_access_moderation_list(): void
    {
        $user = User::factory()->create([
            'role' => 'player',
            'editor_role' => 'none',
        ]);

        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/moderation')
            ->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_dual_approval_requires_two_distinct_reviewers(): void
    {
        $firstReviewer = User::factory()->create([
            'role' => 'manager',
            'editor_role' => 'reviewer',
            'can_dual_approve' => true,
        ]);

        $secondReviewer = User::factory()->create([
            'role' => 'manager',
            'editor_role' => 'senior_reviewer',
            'can_dual_approve' => true,
        ]);

        $item = ModerationQueue::create([
            'model_type' => 'PlayerTransfer',
            'model_id' => 11,
            'status' => 'pending',
            'priority' => 'medium',
            'reason' => 'new_entry',
            'requires_dual_approval' => true,
        ]);

        Sanctum::actingAs($firstReviewer, ['profile:write', 'staff']);

        $this->postJson('/api/moderation/'.$item->id.'/approve', [
            'notes' => 'First approval check',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'First approval recorded, awaiting second approval');

        $this->postJson('/api/moderation/'.$item->id.'/approve', [
            'notes' => 'Same reviewer second approval should fail',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        Sanctum::actingAs($secondReviewer, ['profile:write', 'staff']);

        $this->postJson('/api/moderation/'.$item->id.'/approve', [
            'notes' => 'Second approval granted',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Item approved successfully');
    }

    public function test_critical_items_require_critical_permission(): void
    {
        $reviewer = User::factory()->create([
            'role' => 'manager',
            'editor_role' => 'reviewer',
            'can_verify_critical' => false,
        ]);

        $criticalReviewer = User::factory()->create([
            'role' => 'manager',
            'editor_role' => 'reviewer',
            'can_verify_critical' => true,
        ]);

        $item = ModerationQueue::create([
            'model_type' => 'PlayerMarketValue',
            'model_id' => 21,
            'status' => 'pending',
            'priority' => 'critical',
            'reason' => 'automated_flag',
            'requires_dual_approval' => false,
        ]);

        Sanctum::actingAs($reviewer, ['profile:write', 'staff']);

        $this->postJson('/api/moderation/'.$item->id.'/approve', [
            'notes' => 'No critical permission',
        ])
            ->assertStatus(403)
            ->assertJsonPath('ok', false);

        Sanctum::actingAs($criticalReviewer, ['profile:write', 'staff']);

        $this->postJson('/api/moderation/'.$item->id.'/approve', [
            'notes' => 'Critical permission available',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Item approved successfully');
    }

    public function test_moderation_endpoints_do_not_expose_user_email_fields(): void
    {
        $reviewer = User::factory()->create([
            'role' => 'manager',
            'editor_role' => 'reviewer',
        ]);
        $submitter = User::factory()->create([
            'role' => 'scout',
            'email' => 'submitter@example.com',
        ]);

        $item = ModerationQueue::create([
            'model_type' => 'PlayerTransfer',
            'model_id' => 31,
            'status' => 'pending',
            'priority' => 'medium',
            'reason' => 'new_entry',
            'submitted_by' => $submitter->id,
            'reviewed_by' => $reviewer->id,
            'requires_dual_approval' => false,
        ]);

        Sanctum::actingAs($reviewer, ['profile:read', 'staff']);

        $this->getJson('/api/moderation')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.submitter.id', $submitter->id)
            ->assertJsonMissingPath('data.data.0.submitter.email')
            ->assertJsonMissingPath('data.data.0.reviewer.email');

        $this->getJson('/api/moderation/'.$item->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('data.submitter.email')
            ->assertJsonMissingPath('data.reviewer.email');
    }
}
