<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ScoutPlayerReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScoutPlayerReportController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ((string) $user->role !== 'scout') {
            return $this->errorResponse('Bu alan icin yetkiniz yok.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:review,shortlist,observe,reject'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ScoutPlayerReport::query()
            ->with('player:id,name,sport')
            ->where('scout_user_id', (int) $user->id)
            ->latest('id');

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('player_name', 'like', '%'.$search.'%')
                    ->orWhere('position', 'like', '%'.$search.'%')
                    ->orWhere('club', 'like', '%'.$search.'%')
                    ->orWhere('note', 'like', '%'.$search.'%')
                    ->orWhere('summary', 'like', '%'.$search.'%');
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['min_rating'])) {
            $query->where('rating', '>=', (float) $validated['min_rating']);
        }

        $reports = $query
            ->paginate((int) ($validated['per_page'] ?? 50))
            ->through(fn (ScoutPlayerReport $report) => $this->transformReport($report));

        return $this->successResponse($reports, 'Scout raporlari hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ((string) $user->role !== 'scout') {
            return $this->errorResponse('Scout raporu sadece scout tarafindan kaydedilebilir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'player_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'player_name' => ['nullable', 'string', 'min:2', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'age' => ['nullable', 'integer', 'min:5', 'max:60'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'overall_rating' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:review,shortlist,observe,reject'],
            'recommendation' => ['nullable', 'string', 'in:recommended,highly_recommended,neutral,not_recommended'],
            'club' => ['nullable', 'string', 'max:120'],
            'watched_at' => ['nullable', 'date'],
            'watched_date' => ['nullable', 'date'],
            'watched_location' => ['nullable', 'string', 'max:255'],
            'potential' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:4000'],
            'title' => ['nullable', 'string', 'max:255'],
            'technical_assessment' => ['nullable', 'string', 'max:5000'],
            'strengths' => ['nullable', 'array', 'max:12'],
            'strengths.*' => ['string', 'max:120'],
            'risks' => ['nullable', 'array', 'max:12'],
            'risks.*' => ['string', 'max:120'],
            'note' => ['nullable', 'string', 'min:8', 'max:5000'],
        ]);

        $playerUserId = isset($validated['player_user_id']) ? (int) $validated['player_user_id'] : null;
        $player = null;
        if ($playerUserId !== null) {
            $player = User::query()->find($playerUserId);
            if (! $player || (string) $player->role !== 'player') {
                return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
            }
        }

        $playerName = trim((string) ($validated['player_name'] ?? ($player?->name ?? '')));
        $position = trim((string) ($validated['position'] ?? ($player?->position ?? '')));
        $rating = $validated['rating'] ?? null;
        if ($rating === null && isset($validated['overall_rating'])) {
            $rating = round(((float) $validated['overall_rating']) / 10, 1);
        }
        $status = $validated['status'] ?? $this->mapRecommendationToStatus($validated['recommendation'] ?? null);
        $note = trim((string) ($validated['note'] ?? $validated['technical_assessment'] ?? ''));
        $summary = $this->nullableString($validated['summary'] ?? $validated['title'] ?? null);
        $potential = $this->nullableString($validated['potential'] ?? $validated['watched_location'] ?? null);
        $watchedAt = $validated['watched_at'] ?? $validated['watched_date'] ?? null;

        if ($playerName === '') {
            return $this->errorResponse('Oyuncu adi zorunlu.', Response::HTTP_UNPROCESSABLE_ENTITY, 'missing_player_name');
        }
        if ($position === '') {
            return $this->errorResponse('Pozisyon zorunlu.', Response::HTTP_UNPROCESSABLE_ENTITY, 'missing_position');
        }
        if (! is_numeric($rating)) {
            return $this->errorResponse('Puan zorunlu.', Response::HTTP_UNPROCESSABLE_ENTITY, 'missing_rating');
        }
        if (! in_array($status, ['review', 'shortlist', 'observe', 'reject'], true)) {
            return $this->errorResponse('Rapor durumu zorunlu.', Response::HTTP_UNPROCESSABLE_ENTITY, 'missing_status');
        }
        if (mb_strlen($note) < 8) {
            return $this->errorResponse('Teknik not en az 8 karakter olmali.', Response::HTTP_UNPROCESSABLE_ENTITY, 'short_note');
        }

        $report = ScoutPlayerReport::query()->create([
            'scout_user_id' => (int) $user->id,
            'player_user_id' => $playerUserId,
            'player_name' => $playerName,
            'position' => $position,
            'age' => $validated['age'] ?? null,
            'rating' => round((float) $rating, 1),
            'status' => $status,
            'scout_name' => trim((string) ($user->name ?: 'Scout Ekibi')),
            'club' => $this->nullableString($validated['club'] ?? null),
            'watched_at' => $watchedAt,
            'potential' => $potential,
            'summary' => $summary,
            'strengths' => array_values($validated['strengths'] ?? []),
            'risks' => array_values($validated['risks'] ?? []),
            'note' => $note,
        ]);

        return $this->successResponse($this->transformReport($report->loadMissing('player:id,name,sport')), 'Scout raporu kaydedildi.', Response::HTTP_CREATED);
    }

    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if ((string) $user->role !== 'scout') {
            return $this->errorResponse('Bu alan icin yetkiniz yok.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:review,shortlist,observe,reject'],
        ]);

        $report = ScoutPlayerReport::query()
            ->where('id', $id)
            ->where('scout_user_id', (int) $user->id)
            ->first();
        if (! $report) {
            return $this->errorResponse('Scout raporu bulunamadi.', Response::HTTP_NOT_FOUND, 'report_not_found');
        }

        $report->status = $validated['status'];
        $report->save();

        return $this->successResponse($this->transformReport($report->loadMissing('player:id,name,sport')), 'Scout raporu durumu guncellendi.');
    }

    private function transformReport(ScoutPlayerReport $report): array
    {
        return [
            'id' => $report->id,
            'player_id' => $report->player_user_id,
            'player_name' => $report->player_name,
            'sport' => $report->player?->sport,
            'position' => $report->position,
            'age' => $report->age,
            'rating' => (float) $report->rating,
            'status' => $report->status,
            'scout_name' => $report->scout_name,
            'club' => $report->club,
            'watched_at' => optional($report->watched_at)->toDateString(),
            'potential' => $report->potential,
            'summary' => $report->summary,
            'strengths' => array_values($report->strengths ?? []),
            'risks' => array_values($report->risks ?? []),
            'note' => $report->note,
            'created_at' => optional($report->created_at)->toIso8601String(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function mapRecommendationToStatus(?string $recommendation): ?string
    {
        return match ((string) $recommendation) {
            'highly_recommended', 'recommended' => 'shortlist',
            'neutral' => 'observe',
            'not_recommended' => 'reject',
            default => null,
        };
    }
}
