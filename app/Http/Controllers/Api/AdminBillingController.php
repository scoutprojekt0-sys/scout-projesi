<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\BoostPackage;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBillingController extends Controller
{
    use ApiResponds;

    public function getBoostPackages(): JsonResponse
    {
        $packages = BoostPackage::query()
            ->orderBy('duration_days')
            ->get();

        return $this->successResponse($packages, 'Boost paketleri hazir.');
    }

    public function updateBoostPackage(Request $request, int $packageId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'duration_days' => 'required|integer|min:1|max:365',
            'discover_score' => 'required|integer|min:1|max:1000',
            'active' => 'required|boolean',
        ]);

        $package = BoostPackage::query()->find($packageId);
        if (! $package) {
            return $this->errorResponse('Boost paketi bulunamadi', 404, 'boost_package_not_found');
        }

        $package->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'currency' => strtoupper((string) ($validated['currency'] ?? 'TRY')),
            'duration_days' => $validated['duration_days'],
            'discover_score' => $validated['discover_score'],
            'active' => (bool) $validated['active'],
        ]);

        return $this->successResponse($package->fresh(), 'Boost paketi guncellendi.');
    }

    public function getPayments(Request $request): JsonResponse
    {
        $payments = Payment::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($payments, 'Odeme listesi hazir.');
    }

    public function getSubscriptions(Request $request): JsonResponse
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($subscriptions, 'Abonelik listesi hazir.');
    }

    public function getPaymentStats(): JsonResponse
    {
        $totalRevenue = DB::table('payments')
            ->where('status', 'completed')
            ->sum('amount');

        $paymentCount = DB::table('payments')
            ->where('status', 'completed')
            ->count();

        $activeBoostCount = DB::table('player_boosts')
            ->where('status', 'active')
            ->count();

        $monthlyRevenue = DB::table('payments')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        return $this->successResponse([
            'total_revenue' => $totalRevenue,
            'completed_payments' => $paymentCount,
            'active_subscriptions' => DB::table('subscriptions')->where('status', 'active')->count(),
            'active_boosts' => $activeBoostCount,
            'monthly_revenue' => $monthlyRevenue,
        ], 'Odeme istatistikleri hazir.');
    }
}
