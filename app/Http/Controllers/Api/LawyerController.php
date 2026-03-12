<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\Lawyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LawyerController extends Controller
{
    use ApiResponds;

    public function publicIndex(Request $request): JsonResponse
    {
        $query = Lawyer::query()->with('user:id,name,email,role,city');

        if ($request->filled('specialization'))  { $query->where('specialization', $request->string('specialization')); }
        if ($request->boolean('verified_only'))  { $query->where('is_verified', true); }
        if ($request->filled('min_experience'))  { $query->where('years_experience', '>=', (int) $request->integer('min_experience')); }

        $lawyers = $query->where('is_active', true)
            ->orderByDesc('is_verified')
            ->orderByDesc('id')
            ->paginate(30);

        return $this->paginatedListResponse($lawyers, 'Avukat listesi hazir.');
    }

    public function index(Request $request): JsonResponse
    {
        return $this->publicIndex($request);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_number'   => ['required', 'string', 'max:100', 'unique:lawyers,license_number'],
            'specialization'   => ['required', 'string', 'max:100'],
            'bio'              => ['nullable', 'string', 'max:2000'],
            'office_name'      => ['nullable', 'string', 'max:150'],
            'office_address'   => ['nullable', 'string', 'max:255'],
            'office_phone'     => ['nullable', 'string', 'max:30'],
            'office_email'     => ['nullable', 'email', 'max:120'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'hourly_rate'      => ['nullable', 'numeric', 'min:0'],
            'contract_fee'     => ['nullable', 'numeric', 'min:0'],
        ]);

        if (Lawyer::query()->where('user_id', $request->user()->id)->exists()) {
            return $this->errorResponse('Bu kullanici icin avukat profili zaten mevcut.', Response::HTTP_UNPROCESSABLE_ENTITY, 'lawyer_already_exists');
        }

        $lawyer = Lawyer::query()->create([
            'user_id'        => (int) $request->user()->id,
            ...$validated,
            'is_verified'    => false,
            'is_active'      => true,
            'license_status' => 'valid',
        ]);

        return $this->successResponse($lawyer->load('user:id,name,email,role,city'), 'Avukat profili olusturuldu.');
    }

    public function show(int $lawyerId): JsonResponse
    {
        $lawyer = Lawyer::query()->with('user:id,name,email,role,city')->findOrFail($lawyerId);

        return $this->successResponse($lawyer, 'Avukat profili hazir.');
    }

    public function update(Request $request, int $lawyerId): JsonResponse
    {
        $lawyer = Lawyer::query()->findOrFail($lawyerId);

        if ((int) $lawyer->user_id !== (int) $request->user()->id) {
            return $this->errorResponse('Yetkiniz yok.', Response::HTTP_FORBIDDEN, 'forbidden');
        }

        $validated = $request->validate([
            'bio'              => ['nullable', 'string', 'max:2000'],
            'office_name'      => ['nullable', 'string', 'max:150'],
            'office_address'   => ['nullable', 'string', 'max:255'],
            'office_phone'     => ['nullable', 'string', 'max:30'],
            'office_email'     => ['nullable', 'email', 'max:120'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'hourly_rate'      => ['nullable', 'numeric', 'min:0'],
            'contract_fee'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $lawyer->update($validated);

        return $this->successResponse($lawyer->fresh()->load('user:id,name,email,role,city'), 'Profil guncellendi.');
    }
}
