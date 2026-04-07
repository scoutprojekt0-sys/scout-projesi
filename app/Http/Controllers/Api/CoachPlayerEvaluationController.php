<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\CoachPlayerEvaluation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoachPlayerEvaluationController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array((string) $user->role, ['coach', 'manager', 'scout', 'club', 'team'], true)) {
            return $this->errorResponse('Bu alan sadece teknik ekip kullanicilari icindir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'player_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'player_name' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'in:mine,team'],
        ]);

        $query = CoachPlayerEvaluation::query()
            ->with('author')
            ->latest('id');

        if ((string) ($validated['scope'] ?? 'mine') !== 'team') {
            $query->where('coach_user_id', $user->id);
        }

        if (! empty($validated['player_user_id'])) {
            $query->where('player_user_id', (int) $validated['player_user_id']);
        } elseif (! empty($validated['player_name'])) {
            $query->where('player_name', trim((string) $validated['player_name']));
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('player_name', 'like', '%'.$search.'%')
                    ->orWhere('position', 'like', '%'.$search.'%')
                    ->orWhere('decision_note', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $rows = $query->paginate($perPage)->through(fn (CoachPlayerEvaluation $evaluation) => $this->transformEvaluation($evaluation));

        return $this->successResponse($rows, 'Degerlendirmeler hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array((string) $user->role, ['coach', 'manager', 'scout', 'club', 'team'], true)) {
            return $this->errorResponse('Bu alan sadece teknik ekip kullanicilari icindir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'player_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'player_name' => ['required', 'string', 'min:2', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'decision_note' => ['nullable', 'string', 'max:5000'],
            'scores' => ['required', 'array', 'min:1', 'max:20'],
            'scores.*.label' => ['required', 'string', 'max:120'],
            'scores.*.value' => ['required', 'numeric', 'min:0', 'max:100'],
            'scores.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $playerUserId = isset($validated['player_user_id']) ? (int) $validated['player_user_id'] : null;
        if ($playerUserId !== null) {
            $player = User::query()->find($playerUserId);
            if (! $player || (string) $player->role !== 'player') {
                return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
            }
        }

        $scores = array_map(function (array $item): array {
            return [
                'label' => trim((string) $item['label']),
                'value' => max(0, min(100, (int) round((float) $item['value']))),
                'note' => isset($item['note']) ? trim((string) $item['note']) : '',
            ];
        }, $validated['scores']);

        $average = count($scores) > 0
            ? round(array_sum(array_map(fn (array $item): int => (int) $item['value'], $scores)) / count($scores), 1)
            : 0.0;

        $evaluation = CoachPlayerEvaluation::query()->create([
            'coach_user_id' => $user->id,
            'player_user_id' => $playerUserId,
            'player_name' => trim((string) $validated['player_name']),
            'position' => $this->nullableString($validated['position'] ?? null),
            'decision_note' => $this->nullableString($validated['decision_note'] ?? null),
            'scores' => $scores,
            'average_score' => $average,
            'saved_label' => now()->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i'),
        ]);

        return $this->successResponse(
            $this->transformEvaluation($evaluation),
            'Degerlendirme kaydedildi.',
            Response::HTTP_CREATED
        );
    }

    private function transformEvaluation(CoachPlayerEvaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'player_user_id' => $evaluation->player_user_id,
            'player_name' => $evaluation->player_name,
            'position' => $evaluation->position,
            'decision_note' => $evaluation->decision_note,
            'scores' => $evaluation->scores ?? [],
            'average' => (float) $evaluation->average_score,
            'saved_at' => $evaluation->saved_label ?: optional($evaluation->created_at)->format('d.m.Y H:i'),
            'author_name' => $evaluation->author?->name,
            'author_role' => $evaluation->author?->role,
            'is_mine' => (int) $evaluation->coach_user_id === (int) optional(request()->user())->id,
            'created_at' => optional($evaluation->created_at)->toIso8601String(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
