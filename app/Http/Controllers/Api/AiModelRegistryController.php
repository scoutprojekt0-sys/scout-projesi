<?php

namespace App\Http\Controllers\Api;

use App\Models\AiActiveModel;
use App\Models\AiModelMonitoringSnapshot;
use App\Models\AiModelRollout;
use App\Models\AiTrainingRun;
use App\Services\AiModelRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AiModelRegistryController
{
    public function active(): JsonResponse
    {
        $rows = AiActiveModel::query()
            ->with('trainingRun:id,sport,status,model_version,completed_at')
            ->orderBy('sport')
            ->get()
            ->map(fn (AiActiveModel $model) => [
                'id' => (int) $model->id,
                'sport' => (string) $model->sport,
                'model_version' => (string) $model->model_version,
                'model_path' => $model->model_path,
                'activated_at' => optional($model->activated_at)?->toIso8601String(),
                'training_run' => $model->trainingRun ? [
                    'id' => (int) $model->trainingRun->id,
                    'status' => (string) $model->trainingRun->status,
                    'model_version' => (string) $model->trainingRun->model_version,
                    'completed_at' => optional($model->trainingRun->completed_at)?->toIso8601String(),
                ] : null,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function rollouts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport' => ['nullable', 'in:football,basketball,volleyball'],
        ]);

        $query = AiModelRollout::query()
            ->with('trainingRun:id,sport,status,model_version')
            ->latest('id');

        if (isset($validated['sport'])) {
            $query->where('sport', $validated['sport']);
        }

        $rows = $query->paginate(20)->through(fn (AiModelRollout $row) => [
            'id' => (int) $row->id,
            'sport' => (string) $row->sport,
            'from_model_version' => $row->from_model_version,
            'to_model_version' => (string) $row->to_model_version,
            'action' => (string) $row->action,
            'model_path' => $row->model_path,
            'notes' => $row->notes,
            'rolled_out_at' => optional($row->rolled_out_at)?->toIso8601String(),
            'training_run' => $row->trainingRun ? [
                'id' => (int) $row->trainingRun->id,
                'status' => (string) $row->trainingRun->status,
                'model_version' => (string) $row->trainingRun->model_version,
            ] : null,
        ]);

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function monitoring(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport' => ['nullable', 'in:football,basketball,volleyball'],
        ]);

        $query = AiModelMonitoringSnapshot::query()->latest('id');

        if (isset($validated['sport'])) {
            $query->where('sport', $validated['sport']);
        }

        $rows = $query->paginate(20)->through(fn (AiModelMonitoringSnapshot $row) => [
            'id' => (int) $row->id,
            'sport' => (string) $row->sport,
            'model_version' => (string) $row->model_version,
            'sample_count' => (int) $row->sample_count,
            'drift_detected' => (bool) $row->drift_detected,
            'auto_rollback_executed' => (bool) $row->auto_rollback_executed,
            'rollback_target_model_version' => $row->rollback_target_model_version,
            'metric_summary' => $row->metric_summary,
            'drift_summary' => $row->drift_summary,
            'window_started_at' => optional($row->window_started_at)?->toIso8601String(),
            'window_ended_at' => optional($row->window_ended_at)?->toIso8601String(),
            'captured_at' => optional($row->captured_at)?->toIso8601String(),
        ]);

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function monitoringOverview(): JsonResponse
    {
        $rows = AiActiveModel::query()
            ->with('trainingRun:id,sport,status,model_version,completed_at')
            ->orderBy('sport')
            ->get()
            ->map(function (AiActiveModel $model): array {
                $snapshot = AiModelMonitoringSnapshot::query()
                    ->where('sport', $model->sport)
                    ->where('model_version', $model->model_version)
                    ->latest('id')
                    ->first();

                return [
                    'sport' => (string) $model->sport,
                    'model_version' => (string) $model->model_version,
                    'activated_at' => optional($model->activated_at)?->toIso8601String(),
                    'monitoring' => $snapshot ? [
                        'snapshot_id' => (int) $snapshot->id,
                        'sample_count' => (int) $snapshot->sample_count,
                        'drift_detected' => (bool) $snapshot->drift_detected,
                        'auto_rollback_executed' => (bool) $snapshot->auto_rollback_executed,
                        'rollback_target_model_version' => $snapshot->rollback_target_model_version,
                        'metric_summary' => $snapshot->metric_summary,
                        'captured_at' => optional($snapshot->captured_at)?->toIso8601String(),
                    ] : null,
                    'training_run' => $model->trainingRun ? [
                        'id' => (int) $model->trainingRun->id,
                        'status' => (string) $model->trainingRun->status,
                        'model_version' => (string) $model->trainingRun->model_version,
                        'completed_at' => optional($model->trainingRun->completed_at)?->toIso8601String(),
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    public function publish(Request $request, AiModelRegistryService $registry): JsonResponse
    {
        $validated = $request->validate([
            'sport' => ['required', 'in:football,basketball,volleyball'],
            'model_version' => ['required', 'string', 'max:120'],
            'run_id' => ['nullable', 'integer', 'min:1'],
            'model_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $run = isset($validated['run_id'])
            ? AiTrainingRun::query()->findOrFail((int) $validated['run_id'])
            : null;

        $modelPath = trim((string) ($validated['model_path'] ?? ''));
        if ($modelPath === '') {
            $modelPath = $registry->resolveDefaultModelPath($validated['sport']);
        }

        try {
            $active = $registry->publish(
                $validated['sport'],
                $validated['model_version'],
                $modelPath,
                $run,
                $validated['notes'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Aktif model guncellendi.',
            'data' => [
                'id' => (int) $active->id,
                'sport' => (string) $active->sport,
                'model_version' => (string) $active->model_version,
                'model_path' => $active->model_path,
                'activated_at' => optional($active->activated_at)?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function rollback(Request $request, AiModelRegistryService $registry): JsonResponse
    {
        $validated = $request->validate([
            'sport' => ['required', 'in:football,basketball,volleyball'],
            'model_version' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $active = $registry->rollback(
            $validated['sport'],
            $validated['model_version'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'ok' => true,
            'message' => 'Model rollback tamamlandi.',
            'data' => [
                'id' => (int) $active->id,
                'sport' => (string) $active->sport,
                'model_version' => (string) $active->model_version,
                'model_path' => $active->model_path,
                'activated_at' => optional($active->activated_at)?->toIso8601String(),
            ],
        ]);
    }
}
