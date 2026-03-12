<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBillingController extends Controller
{
    use ApiResponds;

    // Test için fake ödeme oluştur
    public function createTestPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:stripe,paypal',
        ]);

        $payment = Payment::create([
            'user_id'        => $validated['user_id'],
            'subscription_id' => null,
            'amount'         => $validated['amount'],
            'currency'       => 'USD',
            'payment_method' => $validated['payment_method'],
            'transaction_id' => 'test_' . uniqid(),
            'status'         => 'pending',
        ]);

        return $this->successResponse($payment, 'Test odemesi olusturuldu.', 201);
    }

    // Ödemeyi tamamlandı yap (test için)
    public function completeTestPayment(int $paymentId): JsonResponse
    {
        $payment = Payment::find($paymentId);

        if (! $payment) {
            return $this->errorResponse('Odeme bulunamadi', 404, 'payment_not_found');
        }

        $payment->update(['status' => 'completed']);

        // Abonelik oluştur
        if (! $payment->subscription_id) {
            $plan = SubscriptionPlan::first();
            if ($plan) {
                $subscription = Subscription::create([
                    'user_id'              => $payment->user_id,
                    'subscription_plan_id' => $plan->id,
                    'status'               => 'active',
                    'started_at'           => now(),
                    'expires_at'           => now()->addMonth(),
                ]);
                $payment->update(['subscription_id' => $subscription->id]);
            }
        }

        return $this->successResponse($payment->fresh(), 'Odeme tamamlandi');
    }

    // Ödeme listesi (admin)
    public function getPayments(Request $request): JsonResponse
    {
        $payments = Payment::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($payments, 'Odeme listesi hazir.');
    }

    // Abonelik listesi (admin)
    public function getSubscriptions(Request $request): JsonResponse
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($subscriptions, 'Abonelik listesi hazir.');
    }

    // Ödeme istatistikleri
    public function getPaymentStats(): JsonResponse
    {
        $totalRevenue = DB::table('payments')
            ->where('status', 'completed')
            ->sum('amount');

        $paymentCount = DB::table('payments')
            ->where('status', 'completed')
            ->count();

        $subscriptionCount = DB::table('subscriptions')
            ->where('status', 'active')
            ->count();

        $monthlyRevenue = DB::table('payments')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        return $this->successResponse([
                'total_revenue'      => $totalRevenue,
                'completed_payments' => $paymentCount,
                'active_subscriptions' => $subscriptionCount,
                'monthly_revenue'    => $monthlyRevenue,
            ], 'Odeme istatistikleri hazir.');
    }

    // Refund işlemi
    public function refundPayment(int $paymentId): JsonResponse
    {
        $payment = Payment::find($paymentId);

        if (! $payment) {
            return $this->errorResponse('Odeme bulunamadi', 404, 'payment_not_found');
        }

        if ($payment->status !== 'completed') {
            return $this->errorResponse('Sadece tamamlanan odemeler iade edilebilir', 400, 'payment_refund_invalid_state');
        }

        // Real payment provider'a gönder (şu an test)
        $payment->update(['status' => 'refunded']);

        // Subscription'ı iptal et
        if ($payment->subscription_id) {
            Subscription::where('id', $payment->subscription_id)
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return $this->successResponse($payment->fresh(), 'Odeme iade edildi');
    }
}
