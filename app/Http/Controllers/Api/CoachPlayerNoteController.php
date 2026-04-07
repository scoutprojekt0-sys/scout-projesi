<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\CoachPlayerNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoachPlayerNoteController extends Controller
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
            'scope' => ['nullable', 'in:mine,team'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 50);
        $scope = (string) ($validated['scope'] ?? 'mine');

        $query = CoachPlayerNote::query()
            ->with('author')
            ->latest('id');

        if ($scope !== 'team') {
            $query->where('coach_user_id', $user->id);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('player_name', 'like', '%'.$search.'%')
                    ->orWhere('position', 'like', '%'.$search.'%')
                    ->orWhere('tag', 'like', '%'.$search.'%')
                    ->orWhere('focus', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }

        $notes = $query->paginate($perPage)->through(fn (CoachPlayerNote $note) => $this->transformNote($note));

        return $this->successResponse($notes, 'Teknik notlar hazir.');
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
            'tag' => ['nullable', 'string', 'max:120'],
            'focus' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
        ]);

        $playerUserId = isset($validated['player_user_id']) ? (int) $validated['player_user_id'] : null;
        if ($playerUserId !== null) {
            $player = User::query()->find($playerUserId);
            if (! $player || (string) $player->role !== 'player') {
                return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
            }
        }

        $note = CoachPlayerNote::query()->create([
            'coach_user_id' => $user->id,
            'player_user_id' => $playerUserId,
            'player_name' => trim((string) $validated['player_name']),
            'position' => $this->nullableString($validated['position'] ?? null),
            'tag' => $this->nullableString($validated['tag'] ?? null),
            'focus' => $this->nullableString($validated['focus'] ?? null),
            'body' => trim((string) $validated['body']),
        ]);

        return $this->successResponse(
            $this->transformNote($note),
            'Teknik not kaydedildi.',
            Response::HTTP_CREATED
        );
    }

    private function transformNote(CoachPlayerNote $note): array
    {
        return [
            'id' => $note->id,
            'player_user_id' => $note->player_user_id,
            'player' => $note->player_name,
            'position' => $note->position,
            'tag' => $note->tag,
            'focus' => $note->focus,
            'body' => $note->body,
            'author_name' => $note->author?->name,
            'author_role' => $note->author?->role,
            'is_mine' => (int) $note->coach_user_id === (int) optional(request()->user())->id,
            'created_at' => optional($note->created_at)->toIso8601String(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
