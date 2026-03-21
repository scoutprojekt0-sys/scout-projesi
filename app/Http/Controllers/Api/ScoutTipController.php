<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ScoutTip;
use App\Models\User;
use App\Models\VideoClip;
use App\Services\ScoutTipWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        $query = ScoutTip::query()
            ->with(['submitter:id,name,email,scout_points,scout_rank', 'player:id,name', 'videoClip:id,title,video_url'])
            ->orderByDesc('created_at');

        if (! $this->canReview($request->user())) {
            $query->where('submitted_by', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->paginatedListResponse(
            $query->paginate((int) $request->input('per_page', 20)),
            'Scout ihbarlari hazir.'
        );
    }

    public function my(Request $request): JsonResponse
    {
        return $this->paginatedListResponse(
            ScoutTip::query()
                ->with(['videoClip:id,title,video_url', 'duplicateOf:id,player_name,status'])
                ->where('submitted_by', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate((int) $request->input('per_page', 20)),
            'Scout ihbarlariniz hazir.'
        );
    }

    public function feed(Request $request): JsonResponse
    {
        $limit = max(1, min(60, (int) $request->input('limit', 2)));

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
            'metadata' => ['nullable', 'array'],
        ]);

        $tip = $this->workflowService->createTip($request->user(), $validated);

        return $this->successResponse($tip, 'Scout ihbari gonderildi.', Response::HTTP_CREATED);
    }

    public function storeGuest(Request $request): JsonResponse
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
        $metadata = array_merge($validated['metadata'] ?? [], [
            'guest_submission' => true,
            'submitted_via' => 'public_scout_et',
            'guest_ip' => (string) $request->ip(),
            'guest_user_agent' => Str::limit((string) $request->userAgent(), 500),
        ]);

        if (! empty($validated['video_url'])) {
            $clip = VideoClip::create([
                'user_id' => $guestSubmitter->id,
                'title' => $validated['video_title'] ?: ($validated['player_name'].' Scout Clip'),
                'description' => 'Public guest scout tip video',
                'video_url' => $validated['video_url'],
                'platform' => $validated['video_platform'] ?: 'custom',
                'tags' => ['scout_tip', 'guest_submission'],
            ]);
            $validated['video_clip_id'] = $clip->id;
        }

        unset($validated['video_title'], $validated['video_url'], $validated['video_platform']);
        $validated['metadata'] = $metadata;

        $tip = $this->workflowService->createTip($guestSubmitter, $validated);

        return $this->successResponse($tip, 'Misafir scout ihbari sisteme kaydedildi.', Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tip = ScoutTip::with([
            'submitter:id,name,email,scout_points,scout_rank',
            'player:id,name',
            'videoClip',
            'duplicateOf:id,player_name,status',
            'events.actor:id,name',
            'rewards',
        ])->findOrFail($id);

        $this->authorize('view', $tip);

        return $this->successResponse($tip, 'Scout ihbar detayi hazir.');
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
        return $this->transition($request, $id, 'approved', 'Scout ihbari onaylandi.');
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
}
