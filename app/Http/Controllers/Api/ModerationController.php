<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\PlayerTransfer;
use App\Services\ScoutAttributionService;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModerationController extends Controller
{
    public function __construct(
        private readonly ScoutAttributionService $scoutAttributionService,
    ) {
    }

    private function canModerate(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $editorRole = (string) ($user->editor_role ?? 'none');
        $legacyStaffRole = in_array((string) $user->role, ['manager', 'coach', 'scout'], true);

        return in_array($editorRole, ['reviewer', 'senior_reviewer', 'admin'], true) || $legacyStaffRole;
    }

    private function ensureModerationAccess(Request $request): ?JsonResponse
    {
        if ($this->canModerate($request)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Moderasyon yetkiniz yok.',
        ], 403);
    }

    private function ensureCriticalPermission(Request $request, ModerationQueue $item): ?JsonResponse
    {
        if ($item->priority !== 'critical') {
            return null;
        }

        $user = $request->user();
        $editorRole = (string) ($user->editor_role ?? 'none');
        $canVerifyCritical = (bool) ($user->can_verify_critical ?? false);

        if ($canVerifyCritical || in_array($editorRole, ['senior_reviewer', 'admin'], true)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Kritik kayitlar icin ek onay yetkisi gerekli.',
        ], 403);
    }

    private function ensureDualApprovalPermission(Request $request, ModerationQueue $item): ?JsonResponse
    {
        if (! $item->requires_dual_approval || is_null($item->reviewed_by)) {
            return null;
        }

        $user = $request->user();
        $editorRole = (string) ($user->editor_role ?? 'none');
        $canDualApprove = (bool) ($user->can_dual_approve ?? false);

        if ($canDualApprove || in_array($editorRole, ['senior_reviewer', 'admin'], true)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Ikinci onay icin dual-approval yetkisi gerekli.',
        ], 403);
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess($request)) {
            return $denied;
        }

        $query = ModerationQueue::query()
            ->with(['submitter:id,name,email', 'reviewer:id,name,email'])
            ->orderBy('priority', 'desc')
            ->orderBy('submitted_at', 'asc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        $items = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'ok' => true,
            'data' => $items,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess(request())) {
            return $denied;
        }

        $item = ModerationQueue::with([
            'submitter:id,name,email',
            'reviewer:id,name,email',
            'secondReviewer:id,name,email'
        ])->findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $item,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = ModerationQueue::findOrFail($id);

        if ($denied = $this->ensureCriticalPermission($request, $item)) {
            return $denied;
        }

        if ($denied = $this->ensureDualApprovalPermission($request, $item)) {
            return $denied;
        }

        $userId = auth()->id();

        try {
            $approved = $item->approve($userId, $request->notes);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($approved) {
            $this->syncApprovedModelState($item, $userId);

            // Log the approval
            DataAuditLog::logChange(
                $item->model_type,
                $item->model_id,
                'verified',
                $item->current_values,
                $item->proposed_changes,
                $userId,
                'Approved via moderation queue'
            );

            return response()->json([
                'ok' => true,
                'message' => 'Item approved successfully',
                'data' => $item->fresh(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'First approval recorded, awaiting second approval',
            'data' => $item->fresh(),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = ModerationQueue::findOrFail($id);

        if ($denied = $this->ensureCriticalPermission($request, $item)) {
            return $denied;
        }

        $userId = auth()->id();

        $item->reject($userId, $request->reason);
        $this->syncRejectedModelState($item, $userId, $request->reason);

        DataAuditLog::logChange(
            $item->model_type,
            $item->model_id,
            'rejected',
            null,
            null,
            $userId,
            'Rejected: ' . $request->reason
        );

        return response()->json([
            'ok' => true,
            'message' => 'Item rejected successfully',
            'data' => $item->fresh(),
        ]);
    }

    public function flag(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = ModerationQueue::findOrFail($id);
        $userId = auth()->id();

        $item->flag($userId, $request->reason);

        return response()->json([
            'ok' => true,
            'message' => 'Item flagged for review',
            'data' => $item->fresh(),
        ]);
    }

    public function stats(): JsonResponse
    {
        if ($denied = $this->ensureModerationAccess(request())) {
            return $denied;
        }

        $stats = [
            'pending_count' => ModerationQueue::where('status', 'pending')->count(),
            'by_priority' => [
                'critical' => ModerationQueue::where('status', 'pending')
                    ->where('priority', 'critical')->count(),
                'high' => ModerationQueue::where('status', 'pending')
                    ->where('priority', 'high')->count(),
                'medium' => ModerationQueue::where('status', 'pending')
                    ->where('priority', 'medium')->count(),
                'low' => ModerationQueue::where('status', 'pending')
                    ->where('priority', 'low')->count(),
            ],
            'flagged_count' => ModerationQueue::where('status', 'flagged')->count(),
            'oldest_pending' => ModerationQueue::where('status', 'pending')
                ->orderBy('submitted_at', 'asc')
                ->first(['id', 'submitted_at', 'model_type']),
            'approved_today' => ModerationQueue::where('status', 'approved')
                ->whereDate('reviewed_at', today())->count(),
            'rejected_today' => ModerationQueue::where('status', 'rejected')
                ->whereDate('reviewed_at', today())->count(),
        ];

        return response()->json([
            'ok' => true,
            'data' => $stats,
        ]);
    }

    private function syncApprovedModelState(ModerationQueue $item, int $userId): void
    {
        if ($item->model_type !== 'PlayerTransfer') {
            return;
        }

        $transfer = PlayerTransfer::find($item->model_id);
        if (! $transfer) {
            return;
        }

        $transfer->update([
            'verification_status' => 'verified',
            'verified_by' => $userId,
            'verified_at' => now(),
        ]);

        $this->scoutAttributionService->syncTransferVerification($transfer, true);
    }

    private function syncRejectedModelState(ModerationQueue $item, int $userId, string $reason): void
    {
        if ($item->model_type !== 'PlayerTransfer') {
            return;
        }

        $transfer = PlayerTransfer::find($item->model_id);
        if (! $transfer) {
            return;
        }

        $transfer->update([
            'verification_status' => 'rejected',
            'verified_by' => $userId,
            'verified_at' => now(),
            'notes' => trim(($transfer->notes ? $transfer->notes."\n" : '').'Moderation rejected: '.$reason),
        ]);

        $this->scoutAttributionService->syncTransferVerification($transfer, false);
    }
}
