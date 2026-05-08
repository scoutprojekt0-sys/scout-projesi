<?php

namespace Tests\Feature;

use App\Models\AiActiveModel;
use App\Models\AiModelRollout;
use App\Models\AiTrainingRun;
use App\Models\User;
use App\Services\AiModelRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiModelRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_service_publishes_and_rolls_back_models(): void
    {
        $run = AiTrainingRun::query()->create([
            'sport' => 'football',
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'model_version' => 'football-v1',
        ]);

        $registry = app(AiModelRegistryService::class);
        $active = $registry->publish('football', 'football-v1', 'E:/models/football-v1.pt', $run, 'Initial publish');

        $this->assertSame('football-v1', $active->model_version);
        $this->assertDatabaseHas('ai_model_rollouts', [
            'sport' => 'football',
            'to_model_version' => 'football-v1',
            'action' => 'publish',
        ]);

        $registry->publish('football', 'football-v2', 'E:/models/football-v2.pt', null, 'Upgrade');
        $rolledBack = $registry->rollback('football', 'football-v1', 'Rollback');

        $this->assertSame('football-v1', $rolledBack->model_version);
        $this->assertDatabaseHas('ai_model_rollouts', [
            'sport' => 'football',
            'to_model_version' => 'football-v1',
            'action' => 'rollback',
        ]);
    }

    public function test_staff_can_view_active_models_and_rollouts(): void
    {
        $staff = User::factory()->create(['role' => 'scout']);
        $run = AiTrainingRun::query()->create([
            'sport' => 'football',
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'model_version' => 'football-v3',
        ]);

        AiActiveModel::query()->create([
            'sport' => 'football',
            'model_version' => 'football-v3',
            'model_path' => 'E:/models/football-v3.pt',
            'ai_training_run_id' => $run->id,
            'activated_at' => now(),
        ]);

        AiModelRollout::query()->create([
            'sport' => 'football',
            'from_model_version' => 'football-v2',
            'to_model_version' => 'football-v3',
            'action' => 'publish',
            'model_path' => 'E:/models/football-v3.pt',
            'ai_training_run_id' => $run->id,
            'rolled_out_at' => now(),
        ]);

        Sanctum::actingAs($staff, ['staff']);

        $this->getJson('/api/ai-models/active')
            ->assertOk()
            ->assertJsonPath('data.0.model_version', 'football-v3');

        $this->getJson('/api/ai-models/rollouts')
            ->assertOk()
            ->assertJsonPath('data.data.0.to_model_version', 'football-v3');
    }

    public function test_staff_can_publish_and_rollback_models_via_api(): void
    {
        $staff = User::factory()->create(['role' => 'scout']);
        $run = AiTrainingRun::query()->create([
            'sport' => 'football',
            'status' => AiTrainingRun::STATUS_COMPLETED,
            'model_version' => 'football-v4',
        ]);

        Sanctum::actingAs($staff, ['staff']);

        $this->postJson('/api/ai-models/publish', [
            'sport' => 'football',
            'model_version' => 'football-v4',
            'run_id' => $run->id,
            'model_path' => 'E:/models/football-v4.pt',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.model_version', 'football-v4');

        $this->postJson('/api/ai-models/publish', [
            'sport' => 'football',
            'model_version' => 'football-v5',
            'model_path' => 'E:/models/football-v5.pt',
        ])->assertStatus(201);

        $this->postJson('/api/ai-models/rollback', [
            'sport' => 'football',
            'model_version' => 'football-v4',
        ])
            ->assertOk()
            ->assertJsonPath('data.model_version', 'football-v4');

        $this->assertDatabaseHas('ai_active_models', [
            'sport' => 'football',
            'model_version' => 'football-v4',
        ]);
    }
}
