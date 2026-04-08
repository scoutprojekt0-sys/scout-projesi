<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OpportunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hasExpiresAt = Schema::hasColumn('opportunities', 'expires_at');
        $this->closeExpiredOpportunities();

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'position' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
            'age_min' => ['nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['nullable', 'integer', 'min:10', 'max:60'],
            'team_user_id' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'title', 'status', 'city'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('opportunities')
            ->leftJoin('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->select([
                'opportunities.id',
                'opportunities.team_user_id',
                'teams.name as team_name',
                'teams.city as team_city',
                'opportunities.title',
                'opportunities.position',
                'opportunities.age_min',
                'opportunities.age_max',
                'opportunities.min_height',
                'opportunities.dominant_side',
                'opportunities.free_only',
                'opportunities.budget_min',
                'opportunities.budget_max',
                'opportunities.city',
                'opportunities.details',
                'opportunities.status',
                'opportunities.created_at',
                'opportunities.updated_at',
            ]);

        if ($hasExpiresAt) {
            $query->addSelect('opportunities.expires_at');
            $query->where(function ($builder): void {
                $builder
                    ->whereNull('opportunities.expires_at')
                    ->orWhere('opportunities.expires_at', '>', now());
            });
        }

        if (! empty($validated['status'])) {
            $query->where('opportunities.status', $validated['status']);
        }

        if (! empty($validated['position'])) {
            $query->where('opportunities.position', 'like', '%'.$validated['position'].'%');
        }

        if (! empty($validated['city'])) {
            $query->where('opportunities.city', 'like', '%'.$validated['city'].'%');
        }

        if (! empty($validated['team_user_id'])) {
            $query->where('opportunities.team_user_id', (int) $validated['team_user_id']);
        }

        if (! empty($validated['age_min'])) {
            $query->where('opportunities.age_min', '>=', (int) $validated['age_min']);
        }

        if (! empty($validated['age_max'])) {
            $query->where('opportunities.age_max', '<=', (int) $validated['age_max']);
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $sortColumnMap = [
            'created_at' => 'opportunities.created_at',
            'title' => 'opportunities.title',
            'status' => 'opportunities.status',
            'city' => 'opportunities.city',
        ];

        $query->orderBy($sortColumnMap[$sortBy], $sortDir);

        $payload = [
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
                'position' => $validated['position'] ?? null,
                'city' => $validated['city'] ?? null,
                'age_min' => $validated['age_min'] ?? null,
                'age_max' => $validated['age_max'] ?? null,
                'team_user_id' => $validated['team_user_id'] ?? null,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ];

        $cacheEnabled = (bool) config('scout.performance.opportunities_cache_enabled', true);
        if ($cacheEnabled) {
            $ttlSeconds = max(1, (int) config('scout.performance.opportunities_cache_ttl_seconds', 60));
            $version = (int) Cache::get('opportunities:index:cache_version', 1);
            $cacheKey = 'opportunities:index:v'.$version.':'.md5(json_encode([
                'status' => $validated['status'] ?? null,
                'position' => $validated['position'] ?? null,
                'city' => $validated['city'] ?? null,
                'age_min' => $validated['age_min'] ?? null,
                'age_max' => $validated['age_max'] ?? null,
                'team_user_id' => $validated['team_user_id'] ?? null,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'page' => (int) ($validated['page'] ?? 1),
                'per_page' => $perPage,
            ]));

            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json($cached);
            }

            $payload['data'] = $query->paginate($perPage)->toArray();
            Cache::put($cacheKey, $payload, now()->addSeconds($ttlSeconds));

            return response()->json($payload);
        }

        $payload['data'] = $query->paginate($perPage);

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $hasExpiresAt = Schema::hasColumn('opportunities', 'expires_at');
        $authUser = $request->user();
        if (! in_array($authUser->role, ['team', 'club', 'manager', 'coach'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Sadece kulup, takim, menajer veya antrenor rolu ilan olusturabilir.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'position' => ['nullable', 'string', 'max:40'],
            'age_min' => ['nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['nullable', 'integer', 'min:10', 'max:60'],
            'min_height' => ['nullable', 'integer', 'min:120', 'max:250'],
            'dominant_side' => ['nullable', 'string', 'max:20'],
            'free_only' => ['nullable', Rule::in(['any', 'true', 'false'])],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'city' => ['nullable', 'string', 'max:80'],
            'details' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'duration_days' => ['nullable', Rule::in([7, 15, '7', '15'])],
        ]);

        $durationDays = (int) ($validated['duration_days'] ?? 7);
        $expiresAt = now()->addDays($durationDays);

        $payload = [
            'team_user_id' => (int) $authUser->id,
            'title' => $validated['title'],
            'position' => $validated['position'] ?? null,
            'age_min' => $validated['age_min'] ?? null,
            'age_max' => $validated['age_max'] ?? null,
            'min_height' => $validated['min_height'] ?? null,
            'dominant_side' => $validated['dominant_side'] ?? null,
            'free_only' => $validated['free_only'] ?? null,
            'budget_min' => $validated['budget_min'] ?? null,
            'budget_max' => $validated['budget_max'] ?? null,
            'city' => $validated['city'] ?? null,
            'details' => $validated['details'] ?? ($validated['description'] ?? null),
            'status' => $validated['status'] ?? 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($hasExpiresAt) {
            $payload['expires_at'] = $expiresAt;
        }

        $id = DB::table('opportunities')->insertGetId($payload);

        $created = DB::table('opportunities')->where('id', $id)->first();
        $this->bumpIndexCacheVersion();

        return response()->json([
            'ok' => true,
            'message' => 'Ilan olusturuldu.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $hasExpiresAt = Schema::hasColumn('opportunities', 'expires_at');
        $this->closeExpiredOpportunities();

        $query = DB::table('opportunities')
            ->leftJoin('users as teams', 'teams.id', '=', 'opportunities.team_user_id')
            ->where('opportunities.id', $id)
            ->select([
                'opportunities.id',
                'opportunities.team_user_id',
                'teams.name as team_name',
                'teams.city as team_city',
                'opportunities.title',
                'opportunities.position',
                'opportunities.age_min',
                'opportunities.age_max',
                'opportunities.min_height',
                'opportunities.dominant_side',
                'opportunities.free_only',
                'opportunities.budget_min',
                'opportunities.budget_max',
                'opportunities.city',
                'opportunities.details',
                'opportunities.status',
                'opportunities.created_at',
                'opportunities.updated_at',
            ]);

        if ($hasExpiresAt) {
            $query->addSelect('opportunities.expires_at');
        }

        $opportunity = $query->first();

        if (! $opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'ok' => true,
            'data' => $opportunity,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $hasExpiresAt = Schema::hasColumn('opportunities', 'expires_at');
        $opportunity = DB::table('opportunities')->where('id', $id)->first();
        if (! $opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        if ((int) $authUser->id !== (int) $opportunity->team_user_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu ilani guncelleme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'min:3', 'max:160'],
            'position' => ['sometimes', 'nullable', 'string', 'max:40'],
            'age_min' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'min_height' => ['sometimes', 'nullable', 'integer', 'min:120', 'max:250'],
            'dominant_side' => ['sometimes', 'nullable', 'string', 'max:20'],
            'free_only' => ['sometimes', 'nullable', Rule::in(['any', 'true', 'false'])],
            'budget_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'budget_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
            'duration_days' => ['sometimes', 'nullable', Rule::in([7, 15, '7', '15'])],
        ]);

        $updates = [
                'title' => $validated['title'] ?? $opportunity->title,
                'position' => array_key_exists('position', $validated) ? $validated['position'] : $opportunity->position,
                'age_min' => array_key_exists('age_min', $validated) ? $validated['age_min'] : $opportunity->age_min,
                'age_max' => array_key_exists('age_max', $validated) ? $validated['age_max'] : $opportunity->age_max,
                'min_height' => array_key_exists('min_height', $validated) ? $validated['min_height'] : $opportunity->min_height,
                'dominant_side' => array_key_exists('dominant_side', $validated) ? $validated['dominant_side'] : $opportunity->dominant_side,
                'free_only' => array_key_exists('free_only', $validated) ? $validated['free_only'] : $opportunity->free_only,
                'budget_min' => array_key_exists('budget_min', $validated) ? $validated['budget_min'] : $opportunity->budget_min,
                'budget_max' => array_key_exists('budget_max', $validated) ? $validated['budget_max'] : $opportunity->budget_max,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $opportunity->city,
                'details' => array_key_exists('details', $validated)
                    ? $validated['details']
                    : (array_key_exists('description', $validated) ? $validated['description'] : $opportunity->details),
                'status' => $validated['status'] ?? $opportunity->status,
                'updated_at' => now(),
            ];

        if ($hasExpiresAt) {
            $updates['expires_at'] = array_key_exists('duration_days', $validated) && $validated['duration_days']
                ? now()->addDays((int) $validated['duration_days'])
                : ($opportunity->expires_at ?? null);
        }

        DB::table('opportunities')
            ->where('id', $id)
            ->update($updates);

        $updated = DB::table('opportunities')->where('id', $id)->first();
        $this->bumpIndexCacheVersion();

        return response()->json([
            'ok' => true,
            'message' => 'Ilan guncellendi.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $opportunity = DB::table('opportunities')->where('id', $id)->first();
        if (! $opportunity) {
            return response()->json([
                'ok' => false,
                'message' => 'Ilan bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        if ((int) $authUser->id !== (int) $opportunity->team_user_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu ilani silme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::table('opportunities')->where('id', $id)->delete();
        $this->bumpIndexCacheVersion();

        return response()->json([
            'ok' => true,
            'message' => 'Ilan silindi.',
        ]);
    }

    private function bumpIndexCacheVersion(): void
    {
        $key = 'opportunities:index:cache_version';
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }
        Cache::increment($key);
    }

    private function closeExpiredOpportunities(): void
    {
        if (! Schema::hasColumn('opportunities', 'expires_at')) {
            return;
        }

        DB::table('opportunities')
            ->where('status', 'open')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'closed',
                'updated_at' => now(),
            ]);
    }
}
