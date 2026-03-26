<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\CoachPlayerClip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoachPlayerClipController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array((string) $user->role, ['coach', 'manager', 'scout', 'club'], true)) {
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

        $query = CoachPlayerClip::query()
            ->with('author')
            ->latest('id');

        if ($scope !== 'team') {
            $query->where('coach_user_id', $user->id);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('player_name', 'like', '%'.$search.'%')
                    ->orWhere('video_url', 'like', '%'.$search.'%')
                    ->orWhere('stamp', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }

        $clips = $query->paginate($perPage)->through(fn (CoachPlayerClip $clip) => $this->transformClip($clip));

        return $this->successResponse($clips, 'Klip notlari hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array((string) $user->role, ['coach', 'manager', 'scout', 'club'], true)) {
            return $this->errorResponse('Bu alan sadece teknik ekip kullanicilari icindir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'player_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'player_name' => ['required', 'string', 'min:2', 'max:120'],
            'video_url' => ['required', 'url', 'max:2000'],
            'minute_mark' => ['nullable', 'integer', 'min:0', 'max:180'],
            'second_mark' => ['nullable', 'integer', 'min:0', 'max:59'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
        ]);

        $playerUserId = isset($validated['player_user_id']) ? (int) $validated['player_user_id'] : null;
        if ($playerUserId !== null) {
            $player = User::query()->find($playerUserId);
            if (! $player || (string) $player->role !== 'player') {
                return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
            }
        }

        $minute = max(0, (int) ($validated['minute_mark'] ?? 0));
        $second = max(0, min(59, (int) ($validated['second_mark'] ?? 0)));
        $stamp = sprintf('%02d:%02d', $minute, $second);

        $clip = CoachPlayerClip::query()->create([
            'coach_user_id' => $user->id,
            'player_user_id' => $playerUserId,
            'player_name' => trim((string) $validated['player_name']),
            'video_url' => trim((string) $validated['video_url']),
            'minute_mark' => $minute,
            'second_mark' => $second,
            'stamp' => $stamp,
            'body' => trim((string) $validated['body']),
        ]);

        return $this->successResponse(
            $this->transformClip($clip),
            'Klip notu kaydedildi.',
            Response::HTTP_CREATED
        );
    }

    private function transformClip(CoachPlayerClip $clip): array
    {
        return [
            'id' => $clip->id,
            'player_user_id' => $clip->player_user_id,
            'player' => $clip->player_name,
            'url' => $clip->video_url,
            'minute_mark' => $clip->minute_mark,
            'second_mark' => $clip->second_mark,
            'stamp' => $clip->stamp,
            'body' => $clip->body,
            'author_name' => $clip->author?->name,
            'author_role' => $clip->author?->role,
            'is_mine' => (int) $clip->coach_user_id === (int) optional(request()->user())->id,
            'created_at' => optional($clip->created_at)->toIso8601String(),
        ];
    }
}
