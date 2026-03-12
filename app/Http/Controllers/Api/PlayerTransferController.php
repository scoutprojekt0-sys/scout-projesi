<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\PlayerTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PlayerTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlayerTransfer::query()
            ->with(['player:id,name', 'fromClub:id,name', 'toClub:id,name'])
            ->orderBy('transfer_date', 'desc');

        if ($request->has('player_id')) {
            $query->where('player_id', $request->player_id);
        }

        if ($request->has('season')) {
            $query->where('season', $request->season);
        }

        if ($request->has('club_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('from_club_id', $request->club_id)
                  ->orWhere('to_club_id', $request->club_id);
            });
        }

        if ($request->has('transfer_type')) {
            $query->where('transfer_type', $request->transfer_type);
        }

        if ($request->has('verified_only') && $request->verified_only) {
            $query->where('verification_status', 'verified');
        }

        $transfers = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'ok' => true,
            'data' => $transfers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_id' => ['required', Rule::exists('users', 'id')->where('role', 'player')],
            'from_club_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'team')],
            'to_club_id' => ['required', Rule::exists('users', 'id')->where('role', 'team')],
            'fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'transfer_date' => 'required|date',
            'transfer_type' => 'required|in:permanent,loan,free,end_of_loan,unknown',
            'contract_until' => 'nullable|date|after:transfer_date',
            'season' => 'required|string|max:10',
            'window' => 'required|in:summer,winter,special',
            'source_url' => 'required|url|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $transfer = PlayerTransfer::create(array_merge(
            $validator->validated(),
            [
                'created_by' => auth()->id(),
                'verification_status' => 'pending',
                'confidence_score' => 0.7,
            ]
        ));

        // Add to moderation queue
        ModerationQueue::create([
            'model_type' => 'PlayerTransfer',
            'model_id' => $transfer->id,
            'status' => 'pending',
            'priority' => 'medium',
            'reason' => 'new_entry',
            'proposed_changes' => $transfer->toArray(),
            'source_url' => $request->source_url,
            'confidence_score' => 0.7,
            'submitted_by' => auth()->id(),
        ]);

        DataAuditLog::logChange(
            'PlayerTransfer',
            $transfer->id,
            'created',
            null,
            $transfer->toArray(),
            auth()->id(),
            'New transfer record created'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Transfer created successfully. Awaiting verification.',
            'data' => $transfer->load(['player', 'fromClub', 'toClub']),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $transfer = PlayerTransfer::with([
            'player:id,name',
            'fromClub:id,name',
            'toClub:id,name',
            'creator:id,name,email',
            'verifier:id,name,email'
        ])->findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $transfer,
        ]);
    }

    public function timeline(int $playerId): JsonResponse
    {
        $transfers = PlayerTransfer::where('player_id', $playerId)
            ->with(['fromClub:id,name', 'toClub:id,name'])
            ->where('verification_status', 'verified')
            ->orderBy('transfer_date', 'asc')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $transfers,
        ]);
    }
}
