<?php

namespace App\Jobs;

use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $verificationLink = ''
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->user->email)
                ->send(new WelcomeEmail($this->user, $this->verificationLink));

            Log::info('Welcome email sent', ['user_id' => $this->user->id, 'email' => $this->user->email]);
        } catch (\Throwable $e) {
            Log::error('Welcome email failed', [
                'user_id' => $this->user->id,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
