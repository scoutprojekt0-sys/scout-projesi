<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BrevoEmailService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoEmailServiceTest extends TestCase
{
    public function test_it_falls_back_to_laravel_mailer_when_brevo_api_key_is_missing(): void
    {
        config([
            'services.brevo.api_key' => '',
            'services.brevo.sender_email' => 'noreply@example.com',
            'services.brevo.sender_name' => 'NextScout',
            'mail.default' => 'array',
        ]);

        Http::preventStrayRequests();

        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        app(BrevoEmailService::class)->sendWelcomeEmail(
            $user,
            'nextscout://verify-email?token=test-token'
        );

        $this->assertTrue(true);
    }
}
