<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubOffer;
use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\PlayerTransfer;
use App\Services\ScoutAttributionService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PlayerTransferController extends Controller
{
    public function __construct(
        private readonly ScoutAttributionService $scoutAttributionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PlayerTransfer::query()
            ->with(['player:id,name', 'fromClub:id,name', 'toClub:id,name', 'negotiationUpdater:id,name,role'])
            ->orderBy('transfer_date', 'desc');

        if ($user) {
            $this->applyRoleVisibilityScope($query, $user, $request);
        }

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

        $this->scoutAttributionService->attachTransferRewardCandidate($transfer);

        return response()->json([
            'ok' => true,
            'message' => 'Transfer created successfully. Awaiting verification.',
            'data' => $transfer->load(['player', 'fromClub', 'toClub']),
        ], 201);
    }

    public function roomAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $role = strtolower((string) ($user?->role ?? ''));
        $allowedRoles = ['admin', 'manager', 'menajer', 'team', 'club', 'kulup', 'player', 'lawyer'];

        if (!$user || !in_array($role, $allowedRoles, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu aksiyon icin yetkiniz yok.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => ['required', Rule::in(['accept', 'reject', 'counter', 'note'])],
            'counter_fee' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $transfer = PlayerTransfer::with(['player:id,name', 'fromClub:id,name', 'toClub:id,name', 'negotiationUpdater:id,name,role'])->findOrFail($id);
        if (!$this->canAccessTransfer($user, $transfer)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu teklif kaydina erisemezsiniz.',
            ], 403);
        }

        $payload = $validator->validated();
        $action = $payload['action'];
        $note = trim((string) ($payload['note'] ?? ''));
        $historyEntry = '['.Carbon::now()->toDateTimeString()."] {$role}: ";

        if ($action === 'accept') {
            $transfer->negotiation_status = 'accepted';
            $historyEntry .= 'teklif kabul edildi.';
        } elseif ($action === 'reject') {
            $transfer->negotiation_status = 'rejected';
            $historyEntry .= 'teklif reddedildi.';
        } elseif ($action === 'counter') {
            $transfer->negotiation_status = 'countered';
            $transfer->counter_fee = $payload['counter_fee'] ?? null;
            $historyEntry .= 'karsi teklif verildi';
            if ($transfer->counter_fee !== null) {
                $historyEntry .= ' ('.$transfer->counter_fee.' '.$transfer->currency.')';
            }
            $historyEntry .= '.';
        } else {
            $historyEntry .= 'oda notu eklendi.';
        }

        if ($note !== '') {
            $historyEntry .= ' Not: '.$note;
            $transfer->negotiation_notes = trim((string) ($transfer->negotiation_notes ? $transfer->negotiation_notes."\n" : '').$note);
        }

        $transfer->notes = trim((string) ($transfer->notes ? $transfer->notes."\n" : '').$historyEntry);
        $transfer->negotiation_updated_by = $user->id;
        $transfer->negotiation_updated_at = now();
        $transfer->save();

        ClubOffer::query()
            ->where('transfer_id', (int) $transfer->id)
            ->get()
            ->each(function (ClubOffer $offer) use ($transfer, $note): void {
                $offer->status = $transfer->negotiation_status ?: $transfer->verification_status;
                if ($note !== '') {
                    $offer->note = $note;
                }
                $offer->save();
            });

        return response()->json([
            'ok' => true,
            'message' => 'Pazarlik odasi guncellendi.',
            'data' => $transfer->fresh()->load(['player:id,name', 'fromClub:id,name', 'toClub:id,name', 'negotiationUpdater:id,name,role']),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transfer = PlayerTransfer::with([
            'player:id,name',
            'fromClub:id,name',
            'toClub:id,name',
            'creator:id,name,email',
            'verifier:id,name,email',
            'negotiationUpdater:id,name,role',
        ])->findOrFail($id);

        if (!$this->canAccessTransfer($request->user(), $transfer)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu teklif kaydina erisemezsiniz.',
            ], 403);
        }

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

    public function offerDesk(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || strtolower((string) $user->role) !== 'player') {
            return response()->json([
                'ok' => false,
                'message' => 'Sadece oyuncu hesaplari bu ekrana erisebilir.',
            ], 403);
        }

        $rows = DB::table('club_offers as offers')
            ->leftJoin('users as clubs', 'clubs.id', '=', 'offers.club_user_id')
            ->leftJoin('player_transfers as transfers', 'transfers.id', '=', 'offers.transfer_id')
            ->where('offers.target_player_user_id', (int) $user->id)
            ->orderByRaw('COALESCE(transfers.negotiation_updated_at, offers.updated_at, offers.created_at) desc')
            ->select([
                'offers.id',
                'offers.transfer_id',
                'offers.player_name',
                'offers.offer_type',
                'offers.amount_eur',
                'offers.currency',
                'offers.season',
                'offers.contract_years',
                'offers.salary_amount',
                'offers.signing_fee',
                'offers.bonus_summary',
                'offers.contract_start_date',
                'offers.contract_end_date',
                'offers.expires_at',
                'offers.clauses',
                'offers.status',
                'offers.note',
                'offers.created_at',
                'clubs.id as club_id',
                'clubs.name as club_name',
                'clubs.city as club_city',
                'transfers.negotiation_status',
                'transfers.verification_status',
                'transfers.counter_fee',
                'transfers.notes as history_notes',
                'transfers.negotiation_notes',
                'transfers.negotiation_updated_at',
            ])
            ->get()
            ->map(function ($row) {
                $history = collect(explode("\n", (string) ($row->history_notes ?? '')))
                    ->map(fn ($line) => trim((string) $line))
                    ->filter()
                    ->values();

                return [
                    'id' => (int) $row->id,
                    'transfer_id' => $row->transfer_id !== null ? (int) $row->transfer_id : null,
                    'club_id' => $row->club_id !== null ? (int) $row->club_id : null,
                    'club_name' => (string) ($row->club_name ?? 'Kulup'),
                    'club_city' => $row->club_city ? (string) $row->club_city : null,
                    'player_name' => (string) ($row->player_name ?? 'Oyuncu'),
                    'offer_type' => (string) ($row->offer_type ?? 'permanent'),
                    'amount_eur' => $row->amount_eur !== null ? (float) $row->amount_eur : null,
                    'currency' => (string) ($row->currency ?? 'EUR'),
                    'season' => $row->season ? (string) $row->season : null,
                    'contract_years' => $row->contract_years !== null ? (int) $row->contract_years : null,
                    'salary_amount' => $row->salary_amount !== null ? (float) $row->salary_amount : null,
                    'signing_fee' => $row->signing_fee !== null ? (float) $row->signing_fee : null,
                    'bonus_summary' => $row->bonus_summary ? (string) $row->bonus_summary : null,
                    'contract_start_date' => $row->contract_start_date,
                    'contract_end_date' => $row->contract_end_date,
                    'expires_at' => $row->expires_at,
                    'clauses' => $row->clauses ? (string) $row->clauses : null,
                    'status' => (string) ($row->status ?? 'sent'),
                    'note' => $row->note ? (string) $row->note : null,
                    'negotiation_status' => $row->negotiation_status ? (string) $row->negotiation_status : null,
                    'verification_status' => $row->verification_status ? (string) $row->verification_status : null,
                    'counter_fee' => $row->counter_fee !== null ? (float) $row->counter_fee : null,
                    'negotiation_notes' => $row->negotiation_notes ? (string) $row->negotiation_notes : null,
                    'history' => $history,
                    'created_at' => optional($row->created_at)->toISOString(),
                    'updated_at' => optional($row->negotiation_updated_at)->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }

    private function applyRoleVisibilityScope($query, User $user, Request $request): void
    {
        $role = strtolower((string) $user->role);

        if ($role === 'admin') {
            return;
        }

        if ($role === 'player') {
            $request->merge(['player_id' => (string) $user->id]);
            $query->where('player_id', $user->id);
            return;
        }

        if (in_array($role, ['team', 'club', 'kulup'], true)) {
            $request->merge(['club_id' => (string) $user->id]);
            $query->where(function ($builder) use ($user) {
                $builder
                    ->where('from_club_id', $user->id)
                    ->orWhere('to_club_id', $user->id);
            });
            return;
        }

        if (in_array($role, ['manager', 'menajer', 'lawyer'], true)) {
            $query->where(function ($builder) use ($user) {
                $builder
                    ->where('created_by', $user->id)
                    ->orWhere('negotiation_updated_by', $user->id);
            });
        }
    }

    private function canAccessTransfer(?User $user, PlayerTransfer $transfer): bool
    {
        if (!$user) {
            return false;
        }

        $role = strtolower((string) $user->role);

        if ($role === 'admin') {
            return true;
        }

        if ($role === 'player') {
            return (int) $transfer->player_id === (int) $user->id;
        }

        if (in_array($role, ['team', 'club', 'kulup'], true)) {
            return (int) $transfer->from_club_id === (int) $user->id
                || (int) $transfer->to_club_id === (int) $user->id;
        }

        if (in_array($role, ['manager', 'menajer', 'lawyer'], true)) {
            return (int) $transfer->created_by === (int) $user->id
                || (int) $transfer->negotiation_updated_by === (int) $user->id;
        }

        return false;
    }
}
