<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Payment;
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

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            // Ödeme durumunu tamamlandı yap
            $this->payment->update(['status' => 'completed']);

            // Fatura oluştur
            $existing = Invoice::where('payment_id', $this->payment->id)->first();
            if (! $existing) {
                Invoice::create([
                    'user_id'        => $this->payment->user_id,
                    'payment_id'     => $this->payment->id,
                    'invoice_number' => 'INV-' . strtoupper(Str::random(8)) . '-' . $this->payment->id,
                    'amount'         => $this->payment->amount,
                    'currency'       => $this->payment->currency ?? 'USD',
                    'status'         => 'paid',
                    'issued_at'      => now(),
                    'paid_at'        => now(),
                ]);
            }

            // Kullanıcı abonelik durumunu güncelle
            DB::table('users')
                ->where('id', $this->payment->user_id)
                ->update(['subscription_status' => 'active']);

            Log::info('Payment processed successfully', [
                'payment_id' => $this->payment->id,
                'user_id'    => $this->payment->user_id,
                'amount'     => $this->payment->amount,
            ]);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment processing failed', [
            'payment_id' => $this->payment->id,
            'error'      => $exception->getMessage(),
        ]);

        $this->payment->update(['status' => 'failed']);
    }
}
