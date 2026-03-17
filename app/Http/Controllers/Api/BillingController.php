<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\BoostPackage;
use App\Models\Payment;
use App\Models\PlayerBoost;
use App\Services\IyzicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    use ApiResponds;

    public function __construct(private IyzicoService $iyzicoService) {}

    public function boostPackages(): JsonResponse
    {
        $packages = BoostPackage::query()
            ->where('active', true)
            ->orderBy('duration_days')
            ->get();

        return $this->successResponse($packages, 'Boost paketleri hazir.');
    }

    public function boostPurchase(): JsonResponse
    {
        $validated = request()->validate([
            'boost_package_id' => 'required|integer|exists:boost_packages,id',
            'payment_method' => 'nullable|in:iyzico,stripe',
        ]);

        $user = auth()->user();
        $package = BoostPackage::query()
            ->where('active', true)
            ->find($validated['boost_package_id']);

        if (! $package) {
            return $this->errorResponse('Boost paketi aktif degil.', 422, 'boost_package_inactive');
        }

        $paymentMethod = (string) ($validated['payment_method'] ?? 'iyzico');

        $result = DB::transaction(function () use ($user, $package, $paymentMethod): array {
            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => null,
                'boost_package_id' => $package->id,
                'amount' => $package->price,
                'currency' => $package->currency,
                'payment_method' => $paymentMethod,
                'payment_context' => 'boost',
                'transaction_id' => null,
                'status' => 'pending',
                'metadata' => [
                    'purpose' => 'discover_boost',
                    'package_slug' => $package->slug,
                    'provider' => $paymentMethod,
                ],
            ]);

            $boost = PlayerBoost::create([
                'user_id' => $user->id,
                'boost_package_id' => $package->id,
                'payment_id' => $payment->id,
                'status' => 'pending',
                'metadata' => [
                    'package_name' => $package->name,
                    'discover_score' => $package->discover_score,
                ],
            ]);

            return [$payment, $boost];
        });

        [$payment, $boost] = $result;

        if ($paymentMethod === 'iyzico' && $this->iyzicoService->isConfigured()) {
            try {
                $checkout = $this->iyzicoService->initializeBoostCheckout($user, $package, $payment);

                $payment->update([
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'conversation_id' => $checkout['conversation_id'],
                        'checkout_token' => $checkout['token'],
                        'payment_page_url' => $checkout['payment_page_url'],
                    ]),
                ]);

                $payment->refresh();

                return $this->successResponse([
                    'payment' => $payment,
                    'boost' => $boost,
                    'next_action' => 'redirect_to_checkout',
                    'provider' => 'iyzico',
                    'provider_status' => 'checkout_ready',
                    'payment_page_url' => $checkout['payment_page_url'],
                    'checkout_token' => $checkout['token'],
                    'checkout_form_content' => $checkout['checkout_form_content'],
                ], 'Boost odemesi baslatildi.', 201);
            } catch (\Throwable $exception) {
                $payment->update([
                    'status' => 'failed',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'provider_error' => $exception->getMessage(),
                    ]),
                ]);

                $boost->update(['status' => 'failed']);

                return $this->errorResponse($exception->getMessage(), 502, 'boost_checkout_initialize_failed');
            }
        }

        return $this->successResponse([
            'payment' => $payment,
            'boost' => $boost,
            'next_action' => 'provider_checkout_required',
            'provider' => $paymentMethod,
            'provider_status' => 'integration_pending',
        ], 'Boost odemesi baslatildi.', 201);
    }

    public function boostStatus(): JsonResponse
    {
        $user = auth()->user();

        $activeBoost = PlayerBoost::query()
            ->with('package')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('created_at')
            ->first();

        return $this->successResponse(
            $activeBoost ?: ['status' => 'none'],
            'Boost durumu hazir.'
        );
    }

    public function boostHistory(): JsonResponse
    {
        $user = auth()->user();

        $history = PlayerBoost::query()
            ->with(['package', 'payment'])
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->paginate(20);

        return $this->paginatedListResponse($history, 'Boost gecmisi hazir.');
    }

}
