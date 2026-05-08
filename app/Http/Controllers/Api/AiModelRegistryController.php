<?php

namespace App\Http\Controllers\Api;

use App\Models\AiActiveModel;
use App\Models\AiModelRollout;
use App\Models\AiTrainingRun;
use App\Services\AiModelRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $active = $registry->publish(
            $validated['sport'],
            $validated['model_version'],
            $modelPath,
            $run,
            $validated['notes'] ?? null
        );

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
