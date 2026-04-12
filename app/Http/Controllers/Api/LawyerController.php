<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\Lawyer;
use App\Support\ProfileReviewData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LawyerController extends Controller
{
    use ApiResponds;

    public function publicIndex(Request $request): JsonResponse
    {
        $query = Lawyer::query()->with('user:id,name,role,city');

        if ($request->filled('specialization'))  { $query->where('specialization', $request->string('specialization')); }
        if ($request->boolean('verified_only'))  { $query->where('is_verified', true); }
        if ($request->filled('min_experience'))  { $query->where('years_experience', '>=', (int) $request->integer('min_experience')); }

        $lawyers = $query->where('is_active', true)
            ->orderByDesc('is_verified')
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (Lawyer $lawyer) => $this->transformLawyer($lawyer, false));

        return $this->paginatedListResponse($lawyers, 'Avukat listesi hazir.');
    }

    public function index(Request $request): JsonResponse
    {
        return $this->publicIndex($request);
    }

    public function register(Request $request): JsonResponse
    {
        if ((string) $request->user()?->role !== 'lawyer') {
            return $this->errorResponse('Bu alan sadece avukat hesaplari icin aktif.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

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

        return $this->successResponse($this->transformLawyer($lawyer->load('user:id,name,role,city'), true), 'Avukat profili olusturuldu.');
    }

    public function show(Request $request, int $lawyerId): JsonResponse
    {
        $lawyer = Lawyer::query()->with('user:id,name,role,city')->findOrFail($lawyerId);

        return $this->successResponse([
            ...$this->transformLawyer($lawyer, false),
            'reviews' => ProfileReviewData::latestForTarget($lawyer->user_id, $request->user()),
        ], 'Avukat profili hazir.');
    }

    public function update(Request $request, int $lawyerId): JsonResponse
    {
        if ((string) $request->user()?->role !== 'lawyer') {
            return $this->errorResponse('Bu alan sadece avukat hesaplari icin aktif.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

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

        return $this->successResponse($this->transformLawyer($lawyer->fresh()->load('user:id,name,role,city'), true), 'Profil guncellendi.');
    }

    private function transformLawyer(Lawyer $lawyer, bool $includePrivateFields): array
    {
        $payload = [
            'id' => (int) $lawyer->id,
            'user_id' => (int) $lawyer->user_id,
            'specialization' => (string) $lawyer->specialization,
            'bio' => $lawyer->bio,
            'office_name' => $lawyer->office_name,
            'years_experience' => $lawyer->years_experience !== null ? (int) $lawyer->years_experience : null,
            'hourly_rate' => $lawyer->hourly_rate !== null ? (float) $lawyer->hourly_rate : null,
            'contract_fee' => $lawyer->contract_fee !== null ? (float) $lawyer->contract_fee : null,
            'is_verified' => (bool) $lawyer->is_verified,
            'is_active' => (bool) $lawyer->is_active,
            'license_status' => (string) $lawyer->license_status,
            'created_at' => optional($lawyer->created_at)?->toIso8601String(),
            'updated_at' => optional($lawyer->updated_at)?->toIso8601String(),
            'user' => $lawyer->relationLoaded('user') && $lawyer->user ? [
                'id' => (int) $lawyer->user->id,
                'name' => (string) $lawyer->user->name,
                'role' => (string) $lawyer->user->role,
                'city' => (string) ($lawyer->user->city ?? ''),
            ] : null,
        ];

        if ($includePrivateFields) {
            $payload['license_number'] = (string) $lawyer->license_number;
            $payload['office_address'] = $lawyer->office_address;
            $payload['office_phone'] = $lawyer->office_phone;
            $payload['office_email'] = $lawyer->office_email;
        }

        return $payload;
    }
}
