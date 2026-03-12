<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Services\StripeService;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    use ApiResponds;

    public function __construct(
        private StripeService $stripeService,
        private PayPalService $paypalService
    ) {}

    public function plans(): JsonResponse
    {
        $plans = DB::table('subscription_plans')
            ->where('active', true)
            ->orderBy('price')
            ->get();

        return $this->successResponse($plans, 'Abonelik planlari hazir.');
    }

    public function currentSubscription(): JsonResponse
    {
        $user = auth()->user();

        $subscription = DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        return $this->successResponse(
            $subscription ?: ['status' => 'none'],
            'Abonelik durumu hazir.'
        );
    }

    public function subscribe(): JsonResponse
    {
        $validated = request()->validate([
            'plan_id'        => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:stripe,paypal',
        ]);

        $user = auth()->user();
        $plan = DB::table('subscription_plans')->find($validated['plan_id']);

        try {
            $result = $validated['payment_method'] === 'stripe'
                ? $this->stripeService->createSubscription($user, $plan)
                : $this->paypalService->createSubscription($user, $plan);

            return $this->successResponse($result, 'Abonelik olusturuldu.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500, 'subscription_failed');
        }
    }

    public function cancel(): JsonResponse
    {
        $user = auth()->user();

        DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $this->successResponse(null, 'Abonelik iptal edildi.');
    }

    public function payments(): JsonResponse
    {
        $user = auth()->user();

        $payments = DB::table('payments')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->paginatedListResponse($payments, 'Odeme gecmisi hazir.');
    }

    public function invoices(): JsonResponse
    {
        $user = auth()->user();

        $invoices = DB::table('invoices')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->paginatedListResponse($invoices, 'Fatura gecmisi hazir.');
    }
}
