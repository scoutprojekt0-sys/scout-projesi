<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\DataAuditLog;
use App\Models\UserContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContributionController extends Controller
{
    use ApiResponds;

    private function canReview(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $editorRole = (string) ($user->editor_role ?? 'none');
        $legacyStaffRole = in_array((string) $user->role, ['manager', 'coach', 'scout'], true);

        return in_array($editorRole, ['reviewer', 'senior_reviewer', 'admin'], true) || $legacyStaffRole;
    }

    private function ensureReviewAccess(Request $request): ?JsonResponse
    {
        if ($this->canReview($request)) {
            return null;
        }

        return $this->errorResponse('Bu islem icin moderasyon yetkiniz yok.', 403, 'forbidden');
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->ensureReviewAccess($request)) {
            return $denied;
        }

        $query = UserContribution::query()
            ->with(['user:id,name,email', 'reviewer:id,name,email'])
            ->orderBy('created_at', 'desc');

        if ($request->has('user_id'))          { $query->where('user_id', $request->user_id); }
        if ($request->has('status'))           { $query->where('status', $request->status); }
        if ($request->has('model_type'))       { $query->where('model_type', $request->model_type); }
        if ($request->has('contribution_type')){ $query->where('contribution_type', $request->contribution_type); }

        return $this->paginatedListResponse(
            $query->paginate($request->per_page ?? 20),
            'Katki listesi hazir.'
        );
    }

    public function myContributions(Request $request): JsonResponse
    {
        $contributions = UserContribution::where('user_id', auth()->id())
            ->with('reviewer:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->paginatedListResponse($contributions, 'Katkileriniz hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'model_type'        => 'required|string|max:100',
            'model_id'          => 'nullable|integer',
            'contribution_type' => 'required|in:create,update,correction,add_source,add_proof,flag_error',
            'proposed_data'     => 'nullable|array',
            'current_data'      => 'nullable|array',
            'description'       => 'required|string|min:20|max:2000',
            'source_url'        => 'nullable|url|max:500',
            'proof_urls'        => 'nullable|array',
            'proof_urls.*'      => 'url|max:500',
            'reasoning'         => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, 'validation_error', $validator->errors()->toArray());
        }

        $contribution = UserContribution::create(array_merge(
            $validator->validated(),
            ['user_id' => auth()->id(), 'status' => 'pending', 'quality_score' => 0.6]
        ));

        DataAuditLog::logChange(
            'UserContribution', $contribution->id, 'created',
            null, $contribution->toArray(), auth()->id(), 'User contribution submitted'
        );

        return $this->successResponse($contribution, 'Katki gonderildi. Inceleme bekleniyor.', 201);
    }

    public function show(int $id): JsonResponse
    {
        if ($denied = $this->ensureReviewAccess(request())) {
            return $denied;
        }

        $contribution = UserContribution::with([
            'user:id,name,email,editor_role,trust_score',
            'reviewer:id,name,email,editor_role',
        ])->findOrFail($id);

        return $this->successResponse($contribution, 'Katki detayi hazir.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureReviewAccess($request)) {
            return $denied;
        }

        $contribution = UserContribution::findOrFail($id);
        $validated    = $request->validate(['feedback' => 'nullable|string|max:1000']);

        $contribution->approve(auth()->id(), $validated['feedback'] ?? null);

        DataAuditLog::logChange(
            'UserContribution', $contribution->id, 'verified',
            ['status' => 'pending'], ['status' => 'approved'], auth()->id(), 'Contribution approved'
        );

        return $this->successResponse($contribution->fresh(['user', 'reviewer']), 'Katki onaylandi.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureReviewAccess($request)) {
            return $denied;
        }

        $contribution = UserContribution::findOrFail($id);
        $validated    = $request->validate(['reason' => 'required|string|min:10|max:1000']);

        $contribution->reject(auth()->id(), $validated['reason']);

        DataAuditLog::logChange(
            'UserContribution', $contribution->id, 'rejected',
            ['status' => 'pending'], ['status' => 'rejected'],
            auth()->id(), 'Contribution rejected: '.$validated['reason']
        );

        return $this->successResponse($contribution->fresh(['user', 'reviewer']), 'Katki reddedildi.');
    }

    public function requestInfo(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureReviewAccess($request)) {
            return $denied;
        }

        $contribution = UserContribution::findOrFail($id);
        $validated    = $request->validate(['message' => 'required|string|min:10|max:1000']);

        $contribution->requestMoreInfo(auth()->id(), $validated['message']);

        return $this->successResponse($contribution->fresh(['user', 'reviewer']), 'Ek bilgi talep edildi.');
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->has('user_id') ? $request->user_id : auth()->id();

        if ($request->has('user_id') && (int) $request->user_id !== (int) auth()->id()) {
            if ($denied = $this->ensureReviewAccess($request)) {
                return $denied;
            }
        }

        $total    = UserContribution::where('user_id', $userId)->count();
        $approved = UserContribution::where('user_id', $userId)->where('status', 'approved')->count();

        $stats = [
            'total'      => $total,
            'pending'    => UserContribution::where('user_id', $userId)->where('status', 'pending')->count(),
            'approved'   => $approved,
            'rejected'   => UserContribution::where('user_id', $userId)->where('status', 'rejected')->count(),
            'needs_info' => UserContribution::where('user_id', $userId)->where('status', 'needs_info')->count(),
            'accuracy'   => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
        ];

        return $this->successResponse($stats, 'Katki istatistikleri hazir.');
    }
}
