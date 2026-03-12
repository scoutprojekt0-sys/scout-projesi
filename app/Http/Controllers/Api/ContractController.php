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
            ->with(['player:id,name,email', 'club:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginatedListResponse($contracts, 'Sozlesme listesi hazir.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::with(['player:id,name,email', 'club:id,name,email'])->find($id);

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
        $validated = $request->validate([
            'player_id'     => ['required', 'integer', 'exists:users,id'],
            'contract_type' => ['required', 'in:permanent,loan,trial'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after:start_date'],
            'salary'        => ['nullable', 'numeric', 'min:0'],
            'currency'      => ['nullable', 'string', 'size:3'],
            'terms'         => ['nullable', 'string', 'max:5000'],
        ]);

        $contract = Contract::create([
            ...$validated,
            'club_id'  => $request->user()->id,
            'currency' => $validated['currency'] ?? 'USD',
            'status'   => 'active',
        ]);

        return $this->successResponse($contract, 'Sozlesme olusturuldu.', Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::find($id);

        if (! $contract) {
            return $this->errorResponse('Sozlesme bulunamadi.', Response::HTTP_NOT_FOUND, 'contract_not_found');
        }
        if ((int) $contract->club_id !== $user->id) {
            return $this->errorResponse('Sadece kulup tarafi guncelleyebilir.', Response::HTTP_FORBIDDEN, 'forbidden');
        }

        $contract->update($request->validate([
            'status'   => ['sometimes', 'in:active,terminated,expired'],
            'end_date' => ['sometimes', 'date'],
            'salary'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'terms'    => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]));

        return $this->successResponse($contract->fresh(), 'Sozlesme guncellendi.');
    }
}
