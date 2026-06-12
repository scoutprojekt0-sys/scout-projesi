<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ScoutTip;
use App\Models\ScoutTipRoleRequest;
use App\Models\ScoutTipWatchlist;
use App\Models\ProfileReview;
use App\Models\User;
use App\Models\VideoClip;
use App\Services\AiContinuousLearningService;
use App\Services\AiDatasetCandidateService;
use App\Services\ScoutTipWorkflowService;
use App\Support\NotificationStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ScoutTipController extends Controller
{
    use ApiResponds;

    public function __construct(
        private readonly ScoutTipWorkflowService $workflowService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $sportTerms = $this->sportTerms((string) $request->query('sport', ''));
        $query = ScoutTip::query()
            ->with(['submitter:id,name,scout_points,scout_rank', 'player:id,name,sport', 'videoClip:id,title,video_url'])
            ->orderByDesc('created_at');

        if (! $this->canReview($request->user())) {
            $query->where('submitted_by', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $this->applySportTermsToScoutTipQuery($query, $sportTerms);

        return $this->paginatedListResponse(
            $query->paginate((int) $request->input('per_page', 20)),
            'Scout ihbarlari hazir.'
        );
    }

    public function my(Request $request): JsonResponse
    {
        return $this->paginatedListResponse(
            ScoutTip::query()
                ->with(['player:id,name,sport', 'videoClip:id,title,video_url', 'duplicateOf:id,player_name,status'])
                ->where('submitted_by', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate((int) $request->input('per_page', 20)),
            'Scout ihbarlariniz hazir.'
        );
    }

    public function feed(Request $request): JsonResponse
    {
        $limit = max(1, min(60, (int) $request->input('limit', 2)));
        $sportTerms = $this->sportTerms((string) $request->query('sport', ''));

        $rows = ScoutTip::query()
            ->select([
                'id',
                'player_name',
                'city',
                'position',
                'status',
                'final_score',
                'metadata',
                'created_at',
            ])
            ->whereIn('status', ['pending', 'screened', 'shortlisted', 'approved'])
            ->when($sportTerms !== [], function ($query) use ($sportTerms) {
                $this->applySportTermsToScoutTipQuery($query, $sportTerms);
            })
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (ScoutTip $tip) {
                return [
                    'id' => $tip->id,
                    'player_name' => $tip->player_name,
                    'city' => $tip->city,
                    'position' => $tip->position,
                    'status' => $tip->status,
                    'final_score' => $tip->final_score,
                    'created_at' => optional($tip->created_at)->toIso8601String(),
                    'is_guest' => (bool) data_get($tip->metadata, 'guest_submission', false),
                ];
            });

        return $this->successResponse($rows, 'Son ihbarlar hazir.');
    }

    public function resolvePlayer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'position' => ['nullable', 'string', 'max:60'],
        ]);

        $matches = $this->workflowService->findPlayerCandidates(
            name: trim((string) $validated['name']),
            cityHint: isset($validated['city']) ? trim((string) $validated['city']) : null,
            positionHint: isset($validated['position']) ? trim((string) $validated['position']) : null,
        );

        $payload = [
            'best_match' => $matches['best_match'],
            'exact_matches' => $matches['exact_matches']->values(),
            'candidates' => $matches['candidates']->take(8)->values(),
        ];

        return $this->successResponse(
            $payload,
            $matches['best_match'] ? 'Oyuncu eslesmesi bulundu.' : 'Oyuncu eslesmesi bulunamadi.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => ['nullable', 'integer', 'exists:users,id'],
            'video_clip_id' => ['nullable', 'integer', 'exists:video_clips,id'],
            'source_type' => ['required', 'in:new_player,existing_player'],
            'player_name' => ['required', 'string', 'max:160'],
            'birth_year' => ['nullable', 'integer', 'between:1990,'.((int) now()->year)],
            'position' => ['nullable', 'string', 'max:60'],
            'foot' => ['nullable', 'string', 'max:20'],
            'height_cm' => ['nullable', 'integer', 'min:100', 'max:250'],
            'city' => ['required', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'team_name' => ['nullable', 'string', 'max:160'],
            'competition_level' => ['nullable', 'string', 'max:80'],
            'match_date' => ['nullable', 'date'],
            'guardian_consent_status' => ['required', 'in:not_required,pending,received,rejected'],
            'description' => ['required', 'string', 'min:30', 'max:3000'],
            'sport' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ]);

        $resolvedSport = $this->resolveScoutTipSport($validated);
        if ($resolvedSport !== null) {
            $validated['metadata'] = array_merge($validated['metadata'] ?? [], [
                'sport' => $resolvedSport,
            ]);
        }
        unset($validated['sport']);

        $tip = $this->workflowService->createTip($request->user(), $validated);

        return $this->successResponse($tip, 'Scout ihbari gonderildi.', Response::HTTP_CREATED);
    }

    public function storeGuest(
        Request $request,
        AiDatasetCandidateService $datasetCandidateService,
        AiContinuousLearningService $continuousLearningService
    ): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => ['nullable', 'integer', 'exists:users,id'],
            'source_type' => ['required', 'in:new_player,existing_player'],
            'player_name' => ['required', 'string', 'max:160'],
            'birth_year' => ['nullable', 'integer', 'between:1990,'.((int) now()->year)],
            'position' => ['nullable', 'string', 'max:60'],
            'foot' => ['nullable', 'string', 'max:20'],
            'height_cm' => ['nullable', 'integer', 'min:100', 'max:250'],
            'city' => ['required', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'team_name' => ['nullable', 'string', 'max:160'],
            'competition_level' => ['nullable', 'string', 'max:80'],
            'match_date' => ['nullable', 'date'],
            'guardian_consent_status' => ['required', 'in:not_required,pending,received,rejected'],
            'description' => ['required', 'string', 'min:30', 'max:3000'],
            'metadata' => ['nullable', 'array'],
            'video_title' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'url'],
            'video_platform' => ['nullable', 'in:youtube,vimeo,custom'],
        ]);

        $guestSubmitter = $this->resolveGuestSubmitter();
        $resolvedSport = $this->resolveScoutTipSport($validated);
        $metadata = array_merge($validated['metadata'] ?? [], [
            'guest_submission' => true,
            'submitted_via' => 'public_scout_et',
            'guest_ip' => (string) $request->ip(),
            'guest_user_agent' => Str::limit((string) $request->userAgent(), 500),
            'sport' => $resolvedSport,
        ]);

        if (! empty($validated['video_url'])) {
            $tags = array_values(array_filter([
                'scout_tip',
                'guest_submission',
                $resolvedSport,
            ]));
            $clip = VideoClip::create([
                'user_id' => $guestSubmitter->id,
                'title' => $validated['video_title'] ?: ($validated['player_name'].' Scout Clip'),
                'description' => 'Public guest scout tip video',
                'video_url' => $validated['video_url'],
                'platform' => $validated['video_platform'] ?: 'custom',
                'tags' => $tags,
                'metadata' => array_filter([
                    'sport' => $resolvedSport,
                    'ai_dataset_candidate' => $resolvedSport !== null,
                    'ai_dataset_candidate_requested' => $resolvedSport !== null,
                    'candidate_source' => 'guest_scout_tip',
                ], static fn ($value) => $value !== null),
            ]);
            $validated['video_clip_id'] = $clip->id;

            $candidate = $datasetCandidateService->syncFromVideoClip($clip->fresh('player'));
            if ($candidate !== null) {
                $continuousLearningService->onCandidateQueued($candidate);
            }
        }

        unset($validated['video_title'], $validated['video_url'], $validated['video_platform']);
        $validated['metadata'] = $metadata;

        $tip = $this->workflowService->createTip($guestSubmitter, $validated);

        return $this->successResponse($tip, 'Misafir scout ihbari sisteme kaydedildi.', Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tip = ScoutTip::with([
            'submitter:id,name,scout_points,scout_rank',
            'player:id,name',
            'videoClip',
            'duplicateOf:id,player_name,status',
            'events.actor:id,name',
            'rewards',
        ])->findOrFail($id);

        $this->authorize('view', $tip);

        $tip = $this->attachResolvedPlayer($tip);

        return $this->successResponse($tip, 'Scout ihbar detayi hazir.');
    }

    public function saveManagerNote(Request $request, int $id): JsonResponse
    {
        if (! $this->canReview($request->user())) {
            return $this->errorResponse('Scout tip review yetkiniz yok.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:80'],
        ]);

        $tip = ScoutTip::findOrFail($id);
        $metadata = $tip->metadata ?? [];
        $metadata['manager_review'] = array_filter([
            'note' => trim((string) ($validated['note'] ?? '')),
            'next_action' => trim((string) ($validated['next_action'] ?? '')),
            'updated_at' => now()->toIso8601String(),
            'updated_by' => Arr::only($request->user()->toArray(), ['id', 'name', 'role']),
        ], static fn ($value) => ! (is_string($value) && $value === ''));

        $tip->metadata = $metadata;
        $tip->save();

        return $this->successResponse(
            $tip->fresh(['submitter:id,name,scout_points,scout_rank', 'player:id,name', 'videoClip', 'duplicateOf:id,player_name,status', 'events.actor:id,name', 'rewards']),
            'Manager notu kaydedildi.'
        );
    }

    public function watchlist(Request $request): JsonResponse
    {
        $sportTerms = $this->sportTerms((string) $request->query('sport', ''));
        $rows = ScoutTipWatchlist::query()
            ->with([
                'scoutTip.submitter:id,name',
                'scoutTip.roleRequests.user:id,name,role',
                'player:id,name,role,city,position,age,rating,sport',
            ])
            ->where('manager_user_id', $request->user()->id)
            ->when($sportTerms !== [], function ($query) use ($sportTerms) {
                $query->whereHas('player', function ($innerQuery) use ($sportTerms) {
                    $innerQuery->whereIn(DB::raw('LOWER(COALESCE(sport, ""))'), $sportTerms);
                });
            })
            ->latest('id')
            ->get();

        return $this->successResponse($rows, 'Scout tip watchlist hazir.');
    }

    public function staffInbox(Request $request): JsonResponse
    {
        $user = $request->user();
        $sportTerms = $this->sportTerms((string) $request->query('sport', ''));

        if (! $user || ! in_array((string) $user->role, ['coach', 'team', 'club'], true)) {
            return $this->errorResponse('Bu akis sadece kulup ve antrenor hesaplari icin acik.', 403, 'forbidden');
        }

        $rows = ScoutTip::query()
            ->with([
                'submitter:id,name,role',
                'player:id,name,role,city,position,age,rating,sport',
                'videoClip:id,title,video_url',
            ])
            ->whereIn('status', ['shortlisted', 'approved', 'trial', 'signed'])
            ->whereHas('submitter', fn ($query) => $query->where('role', 'manager'))
            ->when($sportTerms !== [], function ($query) use ($sportTerms) {
                $this->applySportTermsToScoutTipQuery($query, $sportTerms);
            })
            ->latest('shortlisted_at')
            ->latest('created_at')
            ->paginate((int) $request->input('per_page', 20));

        $rows->getCollection()->transform(function (ScoutTip $tip) {
            return $this->attachResolvedPlayer($tip);
        });

        return $this->paginatedListResponse($rows, 'Manager kaynakli scout shortlist akisi hazir.');
    }

    public function recordStaffReview(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array((string) $user->role, ['coach', 'team', 'club'], true)) {
            return $this->errorResponse('Bu akis sadece kulup ve antrenor hesaplari icin acik.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:3000'],
            'sentiment' => ['nullable', 'in:olumlu,notr,dikkat'],
        ]);

        $tip = $this->attachResolvedPlayer(ScoutTip::findOrFail($id));
        $player = $tip->player;

        if (! $player || $player->role !== 'player') {
            return $this->errorResponse('Bu scout tip henuz bir oyuncu profiline bagli degil.', 422, 'player_profile_required');
        }

        $reviewedAt = now();
        $comment = trim((string) ($validated['comment'] ?? ''));
        $roleLabel = in_array((string) $user->role, ['team', 'club'], true) ? 'kulup' : 'antrenor';
        $defaultComment = sprintf(
            '%s profili %s tarafindan %s tarihinde incelendi.',
            $player->name ?: 'Oyuncu',
            $user->name ?: ucfirst($roleLabel),
            $reviewedAt->format('d.m.Y H:i')
        );

        $review = ProfileReview::query()->updateOrCreate(
            [
                'author_id' => $user->id,
                'target_id' => $player->id,
            ],
            [
                'author_role' => $user->role,
                'target_role' => $player->role,
                'relationship_type' => in_array((string) $user->role, ['team', 'club'], true) ? 'kulup_sureci' : 'teknik_ekip',
                'sentiment' => $validated['sentiment'] ?? 'notr',
                'body' => $comment !== '' ? $comment : $defaultComment,
                'status' => 'published',
            ]
        );

        $metadata = $tip->metadata ?? [];
        $reviews = collect($metadata['staff_reviews'] ?? [])
            ->reject(fn ($entry) => (int) ($entry['reviewer_id'] ?? 0) === (int) $user->id)
            ->values()
            ->all();
        $reviews[] = [
            'reviewer_id' => (int) $user->id,
            'reviewer_name' => $user->name,
            'reviewer_role' => $user->role,
            'comment' => $comment !== '' ? $comment : null,
            'reviewed_at' => $reviewedAt->toIso8601String(),
        ];
        $metadata['staff_reviews'] = $reviews;
        $tip->metadata = $metadata;
        $tip->save();

        return $this->successResponse([
            'review_id' => $review->id,
            'player_id' => $player->id,
            'player_name' => $player->name,
            'reviewed_at' => $reviewedAt->toIso8601String(),
            'reviewer' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'comment' => $comment !== '' ? $comment : $defaultComment,
        ], 'Inceleme kaydedildi.');
    }

    public function roleRequestFeed(Request $request): JsonResponse
    {
        $user = $request->user();
        $sportTerms = $this->sportTerms((string) $request->query('sport', ''));

        if (! $this->canRequestRole($user)) {
            return $this->errorResponse('Bu akis sadece kulup ve antrenor hesaplari icin acik.', 403, 'forbidden');
        }

        $roleType = $this->normalizeRoleRequestType((string) $user->role);

        $rows = ScoutTip::query()
            ->with([
                'submitter:id,name,role',
                'roleRequests.user:id,name,role',
                'player:id,name,sport',
            ])
            ->whereIn('status', ['pending', 'screened', 'shortlisted', 'approved'])
            ->where(function ($query) use ($user) {
                $query->whereNull('metadata->dismissed_by_user_ids')
                    ->orWhereJsonDoesntContain('metadata->dismissed_by_user_ids', (int) $user->id);
            })
            ->when($sportTerms !== [], function ($query) use ($sportTerms) {
                $this->applySportTermsToScoutTipQuery($query, $sportTerms);
            })
            ->latest('created_at')
            ->paginate((int) $request->input('per_page', 20));

        $rows->getCollection()->transform(function (ScoutTip $tip) use ($user, $roleType) {
            $tip->setAttribute(
                'request_summary',
                [
                    'coach' => $tip->roleRequests->where('role_type', 'coach')->count(),
                    'team' => $tip->roleRequests->where('role_type', 'team')->count(),
                    'mine' => $tip->roleRequests->contains(function (ScoutTipRoleRequest $row) use ($user, $roleType) {
                        return (int) $row->user_id === (int) $user->id && $row->role_type === $roleType;
                    }),
                ]
            );

            return $tip;
        });

        return $this->paginatedListResponse($rows, 'Rol talep akisi hazir.');
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $this->canRequestRole($user)) {
            return $this->errorResponse('Bu akis sadece kulup ve antrenor hesaplari icin acik.', 403, 'forbidden');
        }

        $tip = ScoutTip::findOrFail($id);
        $metadata = $tip->metadata ?? [];

        $dismissedUserIds = collect($metadata['dismissed_by_user_ids'] ?? [])
            ->map(static fn ($value) => (int) $value)
            ->push((int) $user->id)
            ->unique()
            ->values()
            ->all();

        $dismissedUsers = collect($metadata['dismissed_by_users'] ?? [])
            ->reject(fn ($entry) => (int) ($entry['id'] ?? 0) === (int) $user->id)
            ->values()
            ->all();
        $dismissedUsers[] = [
            'id' => (int) $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'dismissed_at' => now()->toIso8601String(),
        ];

        $metadata['dismissed_by_user_ids'] = $dismissedUserIds;
        $metadata['dismissed_by_users'] = $dismissedUsers;
        $tip->metadata = $metadata;
        $tip->save();

        return $this->successResponse([
            'dismissed' => true,
            'scout_tip_id' => $tip->id,
            'dismissed_at' => now()->toIso8601String(),
        ], 'Scout ihbari listenizden kaldirildi.');
    }

    public function myRoleRequests(Request $request): JsonResponse
    {
        $rows = ScoutTipRoleRequest::query()
            ->with([
                'scoutTip.submitter:id,name,role',
                'scoutTip.player:id,name',
            ])
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get();

        return $this->successResponse($rows, 'Rol talepleriniz hazir.');
    }

    public function requestRole(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $this->canRequestRole($user)) {
            return $this->errorResponse('Bu akis sadece kulup ve antrenor hesaplari icin acik.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tip = ScoutTip::with(['submitter:id,name,role', 'roleRequests.user:id,name,role'])->findOrFail($id);
        $roleType = $this->normalizeRoleRequestType((string) $user->role);

        $entry = DB::transaction(function () use ($tip, $user, $roleType, $validated) {
            $entry = ScoutTipRoleRequest::query()->firstOrCreate(
                [
                    'scout_tip_id' => $tip->id,
                    'user_id' => $user->id,
                    'role_type' => $roleType,
                ],
                [
                    'status' => 'requested',
                    'notes' => $validated['notes'] ?? null,
                    'metadata' => [
                        'player_name' => $tip->player_name,
                        'source' => 'scout_tip',
                    ],
                ]
            );

            if (! empty($validated['notes'])) {
                $entry->notes = $validated['notes'];
                $entry->save();
            }

            NotificationStore::sendToUser((int) $tip->submitted_by, 'scout_tip_role_requested', [
                'scout_tip_id' => $tip->id,
                'player_name' => $tip->player_name,
                'requested_role' => $roleType,
                'requested_by' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
            ]);

            return $entry;
        });

        return $this->successResponse(
            $entry->fresh(['scoutTip.submitter:id,name,role', 'user:id,name,role']),
            'Rol talebi kaydedildi.'
        );
    }

    public function addToWatchlist(Request $request, int $id): JsonResponse
    {
        if (! $this->canReview($request->user())) {
            return $this->errorResponse('Scout tip review yetkiniz yok.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tip = $this->attachResolvedPlayer(ScoutTip::findOrFail($id));
        $entry = ScoutTipWatchlist::query()->firstOrCreate(
            [
                'manager_user_id' => $request->user()->id,
                'scout_tip_id' => $tip->id,
            ],
            [
                'player_id' => $tip->player_id,
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
                'metadata' => [
                    'source' => 'scout_tip',
                    'player_name' => $tip->player_name,
                    'position' => $tip->position,
                    'city' => $tip->city,
                ],
            ]
        );

        if ($entry->player_id === null && $tip->player_id) {
            $entry->player_id = $tip->player_id;
        }
        if (! empty($validated['notes'])) {
            $entry->notes = $validated['notes'];
        }
        $entry->save();

        return $this->successResponse(
            $entry->fresh(['scoutTip.submitter:id,name', 'player:id,name,role,city,position,age,rating']),
            'Scout tip watchlist kaydi olusturuldu.'
        );
    }

    public function removeFromWatchlist(Request $request, int $id): JsonResponse
    {
        $entry = ScoutTipWatchlist::query()
            ->where('manager_user_id', $request->user()->id)
            ->findOrFail($id);

        $entry->delete();

        return $this->successResponse(['removed' => true], 'Scout tip watchlist kaydi kaldirildi.');
    }

    public function withdraw(Request $request, int $id): JsonResponse
    {
        $tip = ScoutTip::findOrFail($id);
        $this->authorize('withdraw', $tip);

        $tip = $this->workflowService->updateStatus($tip, $request->user(), 'withdrawn', [
            'notes' => 'Submitter withdrew the scout tip.',
        ]);

        return $this->successResponse($tip, 'Scout ihbari geri cekildi.');
    }

    public function screen(Request $request, int $id): JsonResponse
    {
        if (! $this->canReview($request->user())) {
            return $this->errorResponse('Scout tip review yetkiniz yok.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'review_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'player_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $tip = $this->workflowService->updateStatus(ScoutTip::findOrFail($id), $request->user(), 'screened', $validated);

        return $this->successResponse($tip, 'Scout ihbari tarandi.');
    }

    public function shortlist(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'shortlisted', 'Scout ihbari kisa listeye alindi.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'rejected', 'Scout ihbari reddedildi.');
    }

    public function markTrial(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'trial', 'Scout ihbari deneme asamasina alindi.');
    }

    public function markSigned(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'signed', 'Scout ihbari imza kilometre tasina gecti.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        if (! $this->canReview($request->user())) {
            return $this->errorResponse('Scout tip review yetkiniz yok.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'player_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $tip = $this->workflowService->updateStatus(ScoutTip::findOrFail($id), $request->user(), 'approved', $validated);
        $tip = $this->registerRoleApproval($tip, $request->user(), $validated['notes'] ?? null);

        return $this->successResponse($tip, 'Scout ihbari onaylandi.');
    }

    private function transition(Request $request, int $id, string $status, string $message): JsonResponse
    {
        if (! $this->canReview($request->user())) {
            return $this->errorResponse('Scout tip review yetkiniz yok.', 403, 'forbidden');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'player_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $tip = $this->workflowService->updateStatus(ScoutTip::findOrFail($id), $request->user(), $status, $validated);

        return $this->successResponse($tip, $message);
    }

    private function canReview(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('review', ScoutTip::class);
    }

    private function canRequestRole(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, ['coach', 'team', 'club'], true);
    }

    /**
     * @return list<string>
     */
    private function sportTerms(string $raw): array
    {
        $sport = mb_strtolower(trim($raw));
        if ($sport === '' || $sport === 'all' || $sport === 'coklu spor') {
            return [];
        }

        return match ($sport) {
            'basketbol', 'basketball' => ['basketbol', 'basketball'],
            'voleybol', 'volleyball' => ['voleybol', 'volleyball'],
            default => ['futbol', 'football'],
        };
    }

    private function applySportTermsToScoutTipQuery($query, array $sportTerms): void
    {
        if ($sportTerms === []) {
            return;
        }

        $query->where(function ($sportQuery) use ($sportTerms) {
            $sportQuery
                ->whereIn('metadata->sport', $sportTerms)
                ->orWhereHas('player', function ($innerQuery) use ($sportTerms) {
                    $innerQuery->whereIn(DB::raw('LOWER(COALESCE(sport, ""))'), $sportTerms);
                })
                ->orWhere(function ($unknownSportQuery) {
                    $unknownSportQuery
                        ->whereNull('player_id')
                        ->where(function ($metadataQuery) {
                            $metadataQuery
                                ->whereNull('metadata->sport')
                                ->orWhere('metadata->sport', '');
                        });
                });
        });
    }

    private function normalizeRoleRequestType(string $role): string
    {
        return in_array($role, ['team', 'club'], true) ? 'team' : 'coach';
    }

    private function resolveScoutTipSport(array $payload): ?string
    {
        $directSport = $this->normalizeSport($payload['sport'] ?? null);
        if ($directSport !== null) {
            return $directSport;
        }

        $metadataSport = $this->normalizeSport(data_get($payload, 'metadata.sport'));
        if ($metadataSport !== null) {
            return $metadataSport;
        }

        $playerId = (int) ($payload['player_id'] ?? 0);
        if ($playerId > 0) {
            $playerSport = User::query()->whereKey($playerId)->value('sport');
            $normalizedPlayerSport = $this->normalizeSport($playerSport);
            if ($normalizedPlayerSport !== null) {
                return $normalizedPlayerSport;
            }
        }

        return null;
    }

    private function normalizeSport(mixed $sport): ?string
    {
        if (! is_string($sport)) {
            return null;
        }

        $normalized = strtolower(trim($sport));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'futbol', 'football', 'soccer' => 'football',
            'basketbol', 'basketball' => 'basketball',
            'voleybol', 'volleyball' => 'volleyball',
            default => null,
        };
    }

    private function autoCreateManagerShortlistEntries(ScoutTip $tip): void
    {
        $tip->loadMissing('roleRequests.user:id,name,role');
        $approvals = data_get($tip->metadata, 'role_approvals', []);
        $coachApproved = (bool) data_get($approvals, 'coach.approved', false);
        $teamApproved = (bool) data_get($approvals, 'team.approved', false);

        if (! $coachApproved || ! $teamApproved) {
            return;
        }

        $roleCounts = [
            'coach' => $coachApproved ? 1 : 0,
            'team' => $teamApproved ? 1 : 0,
        ];

        $managerIds = User::query()
            ->where('role', 'manager')
            ->pluck('id');

        foreach ($managerIds as $managerId) {
            ScoutTipWatchlist::query()->firstOrCreate(
                [
                    'manager_user_id' => (int) $managerId,
                    'scout_tip_id' => $tip->id,
                ],
                [
                    'player_id' => $tip->player_id,
                    'status' => 'auto_shortlisted',
                    'notes' => 'Kulup ve antrenor talebi sonrasinda otomatik shortlist olustu.',
                    'metadata' => [
                        'source' => 'dual_role_approval',
                        'player_name' => $tip->player_name,
                        'position' => $tip->position,
                        'city' => $tip->city,
                        'role_approval_counts' => $roleCounts,
                    ],
                ]
            );
        }

        NotificationStore::sendToUsers($managerIds, 'scout_tip_dual_role_match', [
            'scout_tip_id' => $tip->id,
            'player_name' => $tip->player_name,
            'position' => $tip->position,
            'city' => $tip->city,
            'role_approval_counts' => $roleCounts,
        ]);
    }

    private function registerRoleApproval(ScoutTip $tip, User $user, ?string $notes = null): ScoutTip
    {
        if (! in_array((string) $user->role, ['coach', 'team', 'club'], true)) {
            return $this->attachResolvedPlayer($tip);
        }

        $tip = $this->attachResolvedPlayer($tip);
        $roleType = $this->normalizeRoleRequestType((string) $user->role);
        $metadata = $tip->metadata ?? [];
        $approvals = $metadata['role_approvals'] ?? [];
        $approvals[$roleType] = array_filter([
            'approved' => true,
            'approved_at' => now()->toIso8601String(),
            'approved_by' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'notes' => $notes ? trim($notes) : null,
        ], static fn ($value) => $value !== null);

        $metadata['role_approvals'] = $approvals;
        $tip->metadata = $metadata;
        $tip->save();

        if (($approvals['coach']['approved'] ?? false) && ($approvals['team']['approved'] ?? false)) {
            $this->autoCreateManagerShortlistEntries($tip);
        }

        return $this->attachResolvedPlayer($tip);
    }

    private function resolveGuestSubmitter(): User
    {
        return User::firstOrCreate(
            ['email' => 'guest-scout@nextscout.local'],
            [
                'name' => 'Guest Scout Pool',
                'password' => Hash::make(Str::random(32)),
                'role' => 'scout',
                'is_verified' => true,
                'email_verified_at' => now(),
                'subscription_status' => 'free',
                'is_public' => false,
            ]
        );
    }

    private function attachResolvedPlayer(ScoutTip $tip): ScoutTip
    {
        if ($tip->player_id) {
            return $tip->fresh(['submitter:id,name,role,scout_points,scout_rank', 'player:id,name,role,city,position,age,rating', 'videoClip', 'duplicateOf:id,player_name,status', 'events.actor:id,name', 'rewards']);
        }

        $normalized = Str::lower(preg_replace('/\s+/u', '', (string) $tip->player_name) ?? (string) $tip->player_name);
        if ($normalized === '') {
            return $tip->fresh(['submitter:id,name,role,scout_points,scout_rank', 'player:id,name,role,city,position,age,rating', 'videoClip', 'duplicateOf:id,player_name,status', 'events.actor:id,name', 'rewards']);
        }

        $player = User::query()
            ->where('role', 'player')
            ->get(['id', 'name'])
            ->first(function (User $user) use ($normalized) {
                $candidate = Str::lower(preg_replace('/\s+/u', '', (string) $user->name) ?? (string) $user->name);
                return $candidate === $normalized
                    || str_contains($candidate, $normalized)
                    || str_contains($normalized, $candidate);
            });

        if ($player) {
            $tip->player_id = $player->id;
            $tip->save();
        }

        return $tip->fresh(['submitter:id,name,role,scout_points,scout_rank', 'player:id,name,role,city,position,age,rating', 'videoClip', 'duplicateOf:id,player_name,status', 'events.actor:id,name', 'rewards']);
    }
}
