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
        $payments = Payment::query()
            ->with('user:id,name,role')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(function (Payment $payment): array {
                return [
                    'id' => (int) $payment->id,
                    'user' => $payment->user ? [
                        'id' => (int) $payment->user->id,
                        'name' => (string) $payment->user->name,
                        'role' => (string) $payment->user->role,
                    ] : null,
                    'subscription_id' => $payment->subscription_id !== null ? (int) $payment->subscription_id : null,
                    'boost_package_id' => $payment->boost_package_id !== null ? (int) $payment->boost_package_id : null,
                    'amount' => $payment->amount !== null ? (float) $payment->amount : null,
                    'currency' => (string) $payment->currency,
                    'payment_method' => (string) $payment->payment_method,
                    'payment_context' => (string) $payment->payment_context,
                    'status' => (string) $payment->status,
                    'created_at' => optional($payment->created_at)?->toIso8601String(),
                    'updated_at' => optional($payment->updated_at)?->toIso8601String(),
                ];
            });

        return $this->successResponse($payments, 'Odeme listesi hazir.');
    }

    public function getSubscriptions(Request $request): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->with(['user:id,name,role', 'plan:id,name,slug,price,currency,billing_cycle'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(function (Subscription $subscription): array {
                return [
                    'id' => (int) $subscription->id,
                    'user' => $subscription->user ? [
                        'id' => (int) $subscription->user->id,
                        'name' => (string) $subscription->user->name,
                        'role' => (string) $subscription->user->role,
                    ] : null,
                    'plan' => $subscription->plan ? [
                        'id' => (int) $subscription->plan->id,
                        'name' => (string) $subscription->plan->name,
                        'slug' => (string) $subscription->plan->slug,
                        'price' => $subscription->plan->price !== null ? (float) $subscription->plan->price : null,
                        'currency' => (string) $subscription->plan->currency,
                        'billing_cycle' => (string) $subscription->plan->billing_cycle,
                    ] : null,
                    'status' => (string) $subscription->status,
                    'started_at' => optional($subscription->started_at)?->toIso8601String(),
                    'cancelled_at' => optional($subscription->cancelled_at)?->toIso8601String(),
                    'expires_at' => optional($subscription->expires_at)?->toIso8601String(),
                    'created_at' => optional($subscription->created_at)?->toIso8601String(),
                    'updated_at' => optional($subscription->updated_at)?->toIso8601String(),
                ];
            });

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
