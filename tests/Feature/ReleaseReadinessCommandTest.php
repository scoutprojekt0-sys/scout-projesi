<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseReadinessCommandTest extends TestCase
{
    public function test_release_check_fails_for_local_defaults(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.debug', true);
        config()->set('app.key', null);
        config()->set('app.url', 'http://localhost');
        config()->set('scout.frontend_url', 'http://localhost:3000');
        config()->set('scout.cors.allowed_origins', ['http://localhost:3000']);
        config()->set('database.default', 'sqlite');
        config()->set('queue.default', 'sync');
        config()->set('mail.default', 'log');

        $exitCode = Artisan::call('release:check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Critical blockers', $output);
        $this->assertStringContainsString('APP_ENV', $output);
    }

    public function test_release_check_passes_for_production_safe_configuration(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('app.key', 'base64:test-key');
        config()->set('app.url', 'https://api.example.com');
        config()->set('scout.frontend_url', 'https://app.example.com');
        config()->set('scout.cors.allowed_origins', ['https://app.example.com']);
        config()->set('database.default', 'mysql');
        config()->set('queue.default', 'redis');
        config()->set('mail.default', 'smtp');
        config()->set('cache.default', 'redis');
        config()->set('session.driver', 'cookie');
        config()->set('logging.channels.stack.level', 'info');

        putenv('LOG_LEVEL=info');
        putenv('STRIPE_SECRET=test-stripe-secret');
        putenv('STRIPE_WEBHOOK_SECRET=test-stripe-webhook');
        putenv('PAYPAL_CLIENT_ID=test-paypal-client');
        putenv('PAYPAL_SECRET=test-paypal-secret');
        $_ENV['LOG_LEVEL'] = 'info';
        $_ENV['STRIPE_SECRET'] = 'test-stripe-secret';
        $_ENV['STRIPE_WEBHOOK_SECRET'] = 'test-stripe-webhook';
        $_ENV['PAYPAL_CLIENT_ID'] = 'test-paypal-client';
        $_ENV['PAYPAL_SECRET'] = 'test-paypal-secret';
        $_SERVER['LOG_LEVEL'] = 'info';
        $_SERVER['STRIPE_SECRET'] = 'test-stripe-secret';
        $_SERVER['STRIPE_WEBHOOK_SECRET'] = 'test-stripe-webhook';
        $_SERVER['PAYPAL_CLIENT_ID'] = 'test-paypal-client';
        $_SERVER['PAYPAL_SECRET'] = 'test-paypal-secret';

        $exitCode = Artisan::call('release:check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Release check passed', $output);
    }

    public function test_release_check_can_validate_env_file_and_flag_placeholders(): void
    {
        $envFile = base_path('storage/framework/testing-release.env');

        File::put($envFile, implode(PHP_EOL, [
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_KEY=base64:test-key',
            'APP_URL=https://api.example.com',
            'FRONTEND_URL=https://app.example.com',
            'CORS_ALLOWED_ORIGINS=https://app.example.com',
            'DB_CONNECTION=mysql',
            'DB_HOST=${DB_HOST}',
            'DB_PORT=3306',
            'DB_DATABASE=scout_api',
            'DB_USERNAME=scout_user',
            'DB_PASSWORD=${DB_PASSWORD}',
            'CACHE_STORE=redis',
            'QUEUE_CONNECTION=redis',
            'SESSION_DRIVER=cookie',
            'MAIL_MAILER=smtp',
            'MAIL_HOST=smtp.example.com',
            'MAIL_PORT=587',
            'MAIL_USERNAME=${MAIL_USERNAME}',
            'MAIL_PASSWORD=${MAIL_PASSWORD}',
        ]));

        $exitCode = Artisan::call('release:check', ['--env-file' => 'storage/framework/testing-release.env']);
        $output = Artisan::output();

        File::delete($envFile);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Source:', $output);
        $this->assertStringContainsString('DB_HOST', $output);
        $this->assertStringContainsString('MAIL_USERNAME', $output);
    }
}
