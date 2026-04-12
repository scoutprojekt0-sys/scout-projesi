<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContractController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $contracts = Contract::query()
            ->where(fn ($q) => $q->where('player_id', $user->id)->orWhere('club_id', $user->id))
            ->with(['player:id,name', 'club:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginatedListResponse($contracts, 'Sozlesme listesi hazir.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::with(['player:id,name', 'club:id,name'])->find($id);

        if (! $contract) {
            return $this->errorResponse('Sozlesme bulunamadi.', Response::HTTP_NOT_FOUND, 'contract_not_found');
        }
        if ((int) $contract->player_id !== $user->id && (int) $contract->club_id !== $user->id) {
            return $this->errorResponse('Bu sozlesmeye erisim yetkiniz yok.', Response::HTTP_FORBIDDEN, 'forbidden');
        }

        return $this->successResponse($contract, 'Sozlesme detayi hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = strtolower((string) ($user?->role ?? ''));
        $validated = $request->validate([
            'player_id'     => ['nullable', 'integer', 'exists:users,id'],
            'club_id'       => ['nullable', 'integer', 'exists:users,id'],
            'contract_type' => ['required', 'in:permanent,loan,trial'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after:start_date'],
            'salary'        => ['nullable', 'numeric', 'min:0'],
            'currency'      => ['nullable', 'string', 'size:3'],
            'terms'         => ['nullable', 'string', 'max:5000'],
        ]);

        if (! $user || ! in_array($role, ['player', 'team', 'club', 'kulup'], true)) {
            return $this->errorResponse('Bu islem icin yetkiniz yok.', Response::HTTP_FORBIDDEN, 'forbidden');
        }

        if ($role === 'player') {
            if (empty($validated['club_id'])) {
                return $this->errorResponse('Kulup secimi zorunlu.', Response::HTTP_UNPROCESSABLE_ENTITY, 'club_required');
            }
            $validated['player_id'] = $user->id;
        } else {
            if (empty($validated['player_id'])) {
                return $this->errorResponse('Oyuncu secimi zorunlu.', Response::HTTP_UNPROCESSABLE_ENTITY, 'player_required');
            }
            $validated['club_id'] = $user->id;
        }

        $contract = Contract::create([
            ...$validated,
            'currency' => $validated['currency'] ?? 'USD',
            'status'   => 'active',
        ]);

        return $this->successResponse($contract, 'Sozlesme olusturuldu.', Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::find($id);
        $role = strtolower((string) ($user?->role ?? ''));

        if (! $contract) {
            return $this->errorResponse('Sozlesme bulunamadi.', Response::HTTP_NOT_FOUND, 'contract_not_found');
        }
        if (
            (int) $contract->club_id !== (int) $user?->id &&
            (int) $contract->player_id !== (int) $user?->id
        ) {
            return $this->errorResponse('Bu sozlesmeyi guncelleyemezsiniz.', Response::HTTP_FORBIDDEN, 'forbidden');
        }

        $rules = [
            'status' => ['sometimes', 'in:active,terminated,expired'],
            'end_date' => ['sometimes', 'date'],
            'salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'contract_type' => ['sometimes', 'in:permanent,loan,trial'],
            'start_date' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'club_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
        $validated = $request->validate($rules);

        if ($role === 'player') {
            unset($validated['status']);
            unset($validated['salary']);
            if (isset($validated['club_id'])) {
                $contract->club_id = (int) $validated['club_id'];
                unset($validated['club_id']);
            }
        }

        $contract->update($validated);

        return $this->successResponse($contract->fresh(), 'Sozlesme guncellendi.');
    }
}
