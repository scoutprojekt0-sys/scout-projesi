<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlayerBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Payment $payment
    ) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $this->payment->update(['status' => 'completed']);

            $existingInvoice = Invoice::where('payment_id', $this->payment->id)->first();
            if (! $existingInvoice) {
                Invoice::create([
                    'user_id' => $this->payment->user_id,
                    'payment_id' => $this->payment->id,
                    'invoice_number' => 'INV-' . strtoupper(Str::random(8)) . '-' . $this->payment->id,
                    'amount' => $this->payment->amount,
                    'currency' => $this->payment->currency ?? 'USD',
                    'status' => 'paid',
                    'issued_at' => now(),
                    'paid_at' => now(),
                ]);
            }

            if ($this->payment->payment_context === 'boost' && $this->payment->boost_package_id) {
                $this->activateBoost();
            } else {
                DB::table('users')
                    ->where('id', $this->payment->user_id)
                    ->update(['subscription_status' => 'active']);
            }

            Log::info('Payment processed successfully', [
                'payment_id' => $this->payment->id,
                'user_id' => $this->payment->user_id,
                'amount' => $this->payment->amount,
                'payment_context' => $this->payment->payment_context,
            ]);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment processing failed', [
            'payment_id' => $this->payment->id,
            'error' => $exception->getMessage(),
        ]);

        $this->payment->update(['status' => 'failed']);

        if ($this->payment->payment_context === 'boost') {
            PlayerBoost::where('payment_id', $this->payment->id)
                ->update(['status' => 'failed']);
        }
    }

    private function activateBoost(): void
    {
        $package = $this->payment->boostPackage()->first();
        $now = now();
        $endsAt = $package ? $now->copy()->addDays((int) $package->duration_days) : null;

        $boost = PlayerBoost::firstOrNew([
            'payment_id' => $this->payment->id,
        ]);

        $boost->fill([
            'user_id' => $this->payment->user_id,
            'boost_package_id' => $this->payment->boost_package_id,
            'status' => 'active',
            'starts_at' => $now,
            'ends_at' => $endsAt,
            'activated_at' => $now,
            'metadata' => array_merge($boost->metadata ?? [], [
                'activated_via' => 'payment_job',
            ]),
        ]);

        $boost->save();
    }
}
