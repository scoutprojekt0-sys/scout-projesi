<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\EnforcesPrivacy;
use App\Support\ProfileReviewData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PlayerController extends Controller
{
    use EnforcesPrivacy;
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],
            'age_min' => ['nullable', 'integer', 'min:10', 'max:60'],
            'age_max' => ['nullable', 'integer', 'min:10', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentYear = (int) now()->format('Y');
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('users')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.sport',
                'users.gender',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.phone',
                'player_profiles.birth_year',
                'player_profiles.position',
                'player_profiles.dominant_foot',
                'player_profiles.height_cm',
                'player_profiles.weight_kg',
                'player_profiles.current_team',
                'player_profiles.bio',
            ]);

        if (! empty($validated['position'])) {
            $query->where('player_profiles.position', 'like', '%'.$validated['position'].'%');
        }

        if (! empty($validated['city'])) {
            $query->where('users.city', 'like', '%'.$validated['city'].'%');
        }

        if (! empty($validated['age_min'])) {
            $birthYearMax = $currentYear - (int) $validated['age_min'];
            $query->where('player_profiles.birth_year', '<=', $birthYearMax);
        }

        if (! empty($validated['age_max'])) {
            $birthYearMin = $currentYear - (int) $validated['age_max'];
            $query->where('player_profiles.birth_year', '>=', $birthYearMin);
        }

        $players = $query
            ->orderByDesc('users.created_at')
            ->paginate($perPage);

        $authUser = $request->user();
        $canSeePrivate = $this->isAdmin($authUser);

        $players->getCollection()->transform(function ($player) use ($authUser, $canSeePrivate) {
            $isOwner = $authUser && (int) $authUser->id === (int) ($player->id ?? 0);
            return $this->redactPrivateFields($player, $canSeePrivate || $isOwner);
        });

        return response()->json([
            'ok' => true,
            'filters' => [
                'position' => $validated['position'] ?? null,
                'city' => $validated['city'] ?? null,
                'age_min' => $validated['age_min'] ?? null,
                'age_max' => $validated['age_max'] ?? null,
            ],
            'data' => $players,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $player = DB::table('users')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.sport',
                'users.gender',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.phone',
                'users.created_at',
                'player_profiles.birth_year',
                'player_profiles.position',
                'player_profiles.dominant_foot',
                'player_profiles.height_cm',
                'player_profiles.weight_kg',
                'player_profiles.current_team',
                'player_profiles.bio',
            ])
            ->first();

        if (! $player) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        $isOwner = $authUser && (int) $authUser->id === (int) ($player->id ?? 0);
        $player = $this->redactPrivateFields($player, $this->isAdmin($authUser) || $isOwner);

        return response()->json([
            'ok' => true,
            'data' => [
                ...((array) $player),
                'reviews' => ProfileReviewData::latestForTarget($id, $authUser),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = DB::table('users')->where('id', $id)->where('role', 'player')->first();
        if (! $target) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $authUser = $request->user();
        if ((int) $authUser->id !== $id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu profili guncelleme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'sport' => ['sometimes', 'nullable', 'string', 'max:40'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'contract_status' => ['sometimes', 'nullable', Rule::in(['active', 'free'])],
            'seeking_club' => ['sometimes', 'nullable', 'boolean'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'photo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'birth_year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:'.now()->format('Y')],
            'position' => ['sometimes', 'nullable', 'string', 'max:40'],
            'dominant_foot' => ['sometimes', 'nullable', Rule::in(['left', 'right', 'both'])],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'min:120', 'max:230'],
            'weight_kg' => ['sometimes', 'nullable', 'integer', 'min:35', 'max:160'],
            'current_team' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        DB::table('users')
            ->where('id', $id)
            ->where('role', 'player')
            ->update([
                'name' => $validated['name'] ?? $authUser->name,
                'sport' => array_key_exists('sport', $validated) ? $validated['sport'] : $authUser->sport,
                'gender' => array_key_exists('gender', $validated) ? $validated['gender'] : $authUser->gender,
                'contract_status' => array_key_exists('contract_status', $validated) ? $validated['contract_status'] : $authUser->contract_status,
                'seeking_club' => array_key_exists('seeking_club', $validated) ? $validated['seeking_club'] : $authUser->seeking_club,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $authUser->city,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $authUser->phone,
                'age' => array_key_exists('age', $validated) ? $validated['age'] : $authUser->age,
                'rating' => array_key_exists('rating', $validated) ? $validated['rating'] : $authUser->rating,
                'photo_url' => array_key_exists('photo_url', $validated) ? $validated['photo_url'] : $authUser->photo_url,
                'position' => array_key_exists('position', $validated) ? $validated['position'] : $authUser->position,
                'updated_at' => now(),
            ]);

        $existingProfile = DB::table('player_profiles')->where('user_id', $id)->first();

        DB::table('player_profiles')->updateOrInsert(
            ['user_id' => $id],
            [
                'birth_year' => array_key_exists('birth_year', $validated) ? $validated['birth_year'] : ($existingProfile->birth_year ?? null),
                'position' => array_key_exists('position', $validated) ? $validated['position'] : ($existingProfile->position ?? null),
                'dominant_foot' => array_key_exists('dominant_foot', $validated) ? $validated['dominant_foot'] : ($existingProfile->dominant_foot ?? null),
                'height_cm' => array_key_exists('height_cm', $validated) ? $validated['height_cm'] : ($existingProfile->height_cm ?? null),
                'weight_kg' => array_key_exists('weight_kg', $validated) ? $validated['weight_kg'] : ($existingProfile->weight_kg ?? null),
                'current_team' => array_key_exists('current_team', $validated) ? $validated['current_team'] : ($existingProfile->current_team ?? null),
                'bio' => array_key_exists('bio', $validated) ? $validated['bio'] : ($existingProfile->bio ?? null),
                'updated_at' => now(),
            ]
        );

        $updated = DB::table('users')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.sport',
                'users.gender',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.phone',
                'users.age',
                'users.photo_url',
                'users.rating',
                'users.position as user_position',
                'player_profiles.birth_year',
                'player_profiles.position',
                'player_profiles.dominant_foot',
                'player_profiles.height_cm',
                'player_profiles.weight_kg',
                'player_profiles.current_team',
                'player_profiles.bio',
            ])
            ->first();

        return response()->json([
            'ok' => true,
            'message' => 'Oyuncu profili guncellendi.',
            'data' => $updated,
        ]);
    }

    public function publicProfile(int $id): JsonResponse
    {
        $player = DB::table('users')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->where('users.role', 'player')
            ->select([
                'users.id',
                'users.name',
                'users.sport',
                'users.gender',
                'users.is_verified',
                'users.verification_status',
                'users.verified_at',
                'users.contract_status',
                'users.seeking_club',
                'users.city',
                'users.country',
                'users.age',
                'users.position as user_position',
                'users.photo_url',
                'users.rating as user_rating',
                'users.confidence_score',
                'pp.birth_year',
                'pp.position',
                'pp.dominant_foot',
                'pp.height_cm',
                'pp.weight_kg',
                'pp.current_team',
                'pp.bio',
            ])
            ->first();

        if (! $player) {
            return response()->json([
                'ok' => false,
                'message' => 'Oyuncu bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $statsRows = collect();
        if (Schema::hasTable('player_statistics')) {
            $statsRows = DB::table('player_statistics')
                ->where('user_id', $id)
                ->orderByDesc('season')
                ->orderByDesc('id')
                ->get([
                    'season',
                    'league',
                    'matches_played',
                    'matches_started',
                    'matches_benched',
                    'goals',
                    'assists',
                    'minutes_played',
                    'avg_rating',
                ]);
        }

        $latest = $statsRows->first();
        $summary = [
            'matches' => (int) $statsRows->sum('matches_played'),
            'goals' => (int) $statsRows->sum('goals'),
            'assists' => (int) $statsRows->sum('assists'),
            'minutes' => (int) $statsRows->sum('minutes_played'),
            'rating' => $latest?->avg_rating !== null ? (float) $latest->avg_rating : (float) ($player->user_rating ?? 0),
        ];
        $talentMetrics = $this->buildTalentMetrics($summary);

        $position = $player->position ?: $player->user_position ?: 'Oyuncu';
        $age = $player->age;
        if (! $age && $player->birth_year) {
            $age = (int) now()->format('Y') - (int) $player->birth_year;
        }
        $verificationStatus = strtolower((string) ($player->verification_status ?? ''));
        $isVerified = (bool) ($player->is_verified ?? false)
            || $verificationStatus === 'verified'
            || ! empty($player->verified_at);

        return response()->json([
            'ok' => true,
            'data' => [
                'user' => [
                    'id' => (int) $player->id,
                    'name' => (string) $player->name,
                    'sport' => (string) ($player->sport ?: 'futbol'),
                    'gender' => (string) ($player->gender ?: 'bay'),
                ],
                'profile' => [
                    'sport' => (string) ($player->sport ?: 'futbol'),
                    'branch' => (string) ($player->sport ?: 'futbol'),
                    'gender' => (string) ($player->gender ?: 'bay'),
                    'position' => (string) $position,
                    'age' => $age !== null ? (int) $age : null,
                    'height_cm' => $player->height_cm ? (int) $player->height_cm : null,
                    'weight_kg' => $player->weight_kg ? (int) $player->weight_kg : null,
                    'current_club' => (string) ($player->current_team ?? '-'),
                    'club_name' => (string) ($player->current_team ?? '-'),
                    'bio' => (string) ($player->bio ?? ''),
                    'dominant_foot' => $player->dominant_foot,
                    'contract_status' => (string) ($player->contract_status ?: 'active'),
                    'seeking_club' => (bool) ($player->seeking_club ?? false),
                    'nationality' => (string) ($player->country ?? ''),
                    'city' => (string) ($player->city ?? ''),
                    'is_verified' => $isVerified,
                    'verification_status' => $verificationStatus ?: null,
                ],
                'card' => [
                    'id' => (int) $player->id,
                    'position' => (string) $position,
                    'age' => $age !== null ? (int) $age : null,
                    'height' => $player->height_cm ? ((int) $player->height_cm).'cm' : '-',
                    'overall_rating' => $summary['rating'],
                    'matches_played' => $summary['matches'],
                    'goals' => $summary['goals'],
                    'assists' => $summary['assists'],
                    'nationality' => (string) ($player->country ?? ''),
                    'profile_photo_url' => (string) ($player->photo_url ?? ''),
                    'confidence_score' => $player->confidence_score !== null ? (float) $player->confidence_score : null,
                    'is_verified' => $isVerified,
                    'verification_status' => $verificationStatus ?: null,
                    'talent_metrics' => $talentMetrics,
                ],
                'stats' => [
                    'summary' => $summary,
                    'latest' => $latest ? [
                        'season' => $latest->season,
                        'league' => $latest->league,
                        'matches_played' => (int) ($latest->matches_played ?? 0),
                        'minutes_played' => (int) ($latest->minutes_played ?? 0),
                        'goals' => (int) ($latest->goals ?? 0),
                        'assists' => (int) ($latest->assists ?? 0),
                        'rating' => $latest->avg_rating !== null ? (float) $latest->avg_rating : $summary['rating'],
                    ] : null,
                    'history' => $statsRows->map(fn ($row) => [
                        'season' => $row->season,
                        'league' => $row->league,
                        'matches_played' => (int) ($row->matches_played ?? 0),
                        'matches_started' => (int) ($row->matches_started ?? 0),
                        'matches_benched' => (int) ($row->matches_benched ?? 0),
                        'minutes_played' => (int) ($row->minutes_played ?? 0),
                        'goals' => (int) ($row->goals ?? 0),
                        'assists' => (int) ($row->assists ?? 0),
                        'avg_rating' => $row->avg_rating !== null ? (float) $row->avg_rating : null,
                    ])->values(),
                ],
            ],
        ]);
    }

    private function buildTalentMetrics(array $summary): array
    {
        $matches = (int) ($summary['matches'] ?? 0);
        $goals = (int) ($summary['goals'] ?? 0);
        $assists = (int) ($summary['assists'] ?? 0);
        $minutes = (int) ($summary['minutes'] ?? 0);
        $rating = (float) ($summary['rating'] ?? 0);

        $minutesPerMatch = $matches > 0 ? $minutes / $matches : 0.0;
        $goalRate = $matches > 0 ? $goals / $matches : 0.0;
        $assistRate = $matches > 0 ? $assists / $matches : 0.0;
        $involvementRate = $matches > 0 ? ($goals + $assists) / $matches : 0.0;
        $ratingMomentum = max(-12.0, min(12.0, ($rating - 7.0) * 6));
        $minutesRatio = max(0.0, min(1.0, $minutesPerMatch / 90));
        $minutesBlocks = max(0.0, min(40.0, $minutes / 90));

        return [
            [
                'label' => 'Topla Oyun',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(40, [
                        $rating * 2.8,
                        $ratingMomentum,
                        $assistRate * 18,
                        $involvementRate * 8,
                        $minutesRatio * 16,
                    ])
                ),
            ],
            [
                'label' => 'Bitiricilik',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(38, [
                        $rating * 2.0,
                        $ratingMomentum,
                        $goalRate * 34,
                        $goals * 1.2,
                        $minutesRatio * 10,
                    ])
                ),
            ],
            [
                'label' => 'Oyun Kurulum',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(38, [
                        $rating * 2.2,
                        $ratingMomentum,
                        $assistRate * 36,
                        $involvementRate * 10,
                        $minutesRatio * 12,
                    ])
                ),
            ],
            [
                'label' => 'Mac Etkisi',
                'value' => $this->normalizeTalentMetric(
                    $this->metricScore(35, [
                        $matches * 0.9,
                        $minutesBlocks * 0.6,
                        $involvementRate * 12,
                        $rating * 1.6,
                        $ratingMomentum,
                    ])
                ),
            ],
        ];
    }

    private function metricScore(float $base, array $factors): float
    {
        $total = $base + array_sum($factors);
        if ($total < 0) {
            return 0.0;
        }
        if ($total > 95) {
            return 95.0;
        }

        return $total;
    }

    private function normalizeTalentMetric(float $raw): float
    {
        $normalized = $raw / 100;
        if ($normalized < 0.45) {
            return 0.45;
        }
        if ($normalized > 0.95) {
            return 0.95;
        }

        return round($normalized, 4);
    }
}
