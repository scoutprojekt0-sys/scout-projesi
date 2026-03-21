<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('legacy-auth:repair {--dry-run : Show affected users without writing changes}', function () {
    $cutoff = Carbon::create(2026, 3, 11, 9, 0, 0, config('app.timezone', 'UTC'));

    $legacyUsers = User::query()
        ->where(function ($query) {
            $query->where('is_verified', false)->orWhereNull('is_verified');
        })
        ->whereNull('email_verified_at')
        ->whereNull('email_verification_token')
        ->where('created_at', '<', $cutoff)
        ->orderBy('id')
        ->get();

    $demoUsers = [
        'player@test.com' => [
            'name' => 'Test Oyuncu',
            'password' => 'Password123!',
            'role' => 'player',
            'city' => 'Istanbul',
        ],
        'team@test.com' => [
            'name' => 'Test Takim',
            'password' => 'Password123!',
            'role' => 'team',
            'city' => 'Ankara',
        ],
        'scout@test.com' => [
            'name' => 'Test Scout',
            'password' => 'Password123!',
            'role' => 'scout',
            'city' => 'Izmir',
        ],
        'club-a@nextscout.pro' => [
            'name' => 'Istanbul Athletic',
            'password' => 'Password123',
            'role' => 'team',
            'city' => 'Istanbul',
        ],
        'club-b@nextscout.pro' => [
            'name' => 'Ankara United',
            'password' => 'Password123',
            'role' => 'team',
            'city' => 'Ankara',
        ],
        'player-demo@nextscout.pro' => [
            'name' => 'Demir Yilmaz',
            'password' => 'Password123',
            'role' => 'player',
            'city' => 'Istanbul',
        ],
    ];

    $this->info('Legacy auth repair scan');
    $this->line('Legacy users to verify: '.$legacyUsers->count());
    foreach ($legacyUsers as $user) {
        $this->line(sprintf(' - #%d %s', $user->id, $user->email));
    }

    $this->newLine();
    $this->line('Demo users to normalize: '.count($demoUsers));
    foreach (array_keys($demoUsers) as $email) {
        $this->line(' - '.$email);
    }

    if ($this->option('dry-run')) {
        $this->newLine();
        $this->comment('Dry run only. No changes were written.');

        return SymfonyCommand::SUCCESS;
    }

    $updatedLegacyCount = 0;
    foreach ($legacyUsers as $user) {
        $user->forceFill([
            'is_verified' => true,
            'email_verified_at' => $user->created_at ?? now(),
        ])->save();
        $updatedLegacyCount++;
    }

    foreach ($demoUsers as $email => $profile) {
        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $profile['name'],
                'password' => Hash::make($profile['password']),
                'role' => $profile['role'],
                'city' => $profile['city'],
                'is_verified' => true,
                'email_verified_at' => now(),
                'email_verification_token' => null,
            ]
        );
    }

    $this->newLine();
    $this->info("Repaired {$updatedLegacyCount} legacy users.");
    $this->info('Normalized known demo accounts and passwords.');
    $this->line('Demo credentials:');
    $this->line(' - player@test.com / Password123!');
    $this->line(' - team@test.com / Password123!');
    $this->line(' - scout@test.com / Password123!');
    $this->line(' - club-a@nextscout.pro / Password123');
    $this->line(' - club-b@nextscout.pro / Password123');
    $this->line(' - player-demo@nextscout.pro / Password123');

    return SymfonyCommand::SUCCESS;
})->purpose('Repair legacy users and normalize demo account credentials');

Artisan::command('legacy-auth:set-password {email} {password} {--verify : Mark the user as verified too}', function (string $email, string $password) {
    $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();

    if (! $user) {
        $this->error('User not found: '.$email);

        return SymfonyCommand::FAILURE;
    }

    $updates = [
        'password' => Hash::make($password),
    ];

    if ($this->option('verify')) {
        $updates['is_verified'] = true;
        $updates['email_verified_at'] = $user->email_verified_at ?? now();
        $updates['email_verification_token'] = null;
    }

    $user->forceFill($updates)->save();

    $this->info('Password updated for '.$user->email);
    if ($this->option('verify')) {
        $this->line('User was also marked as verified.');
    }

    return SymfonyCommand::SUCCESS;
})->purpose('Set a temporary password for a specific legacy user');

Artisan::command('release:check {--env-file=}', function () {
    $isPublicUrl = static function (?string $value): bool {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $host = parse_url(trim($value), PHP_URL_HOST);
        $scheme = parse_url(trim($value), PHP_URL_SCHEME);

        if (! is_string($host) || $host === '') {
            return false;
        }

        return strtolower((string) $scheme) === 'https'
            && ! in_array(strtolower($host), ['localhost', '127.0.0.1'], true);
    };

    $hasOnlyPublicOrigins = static function (array $origins) use ($isPublicUrl): bool {
        if ($origins === []) {
            return false;
        }

        foreach ($origins as $origin) {
            if (! $isPublicUrl($origin)) {
                return false;
            }
        }

        return true;
    };

    $parseEnvFile = static function (string $path): array {
        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return $values;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
                ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    };

    $isResolvedValue = static function (?string $value): bool {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        return ! preg_match('/^\$\{[^}]+\}$/', trim($value));
    };

    $envFile = $this->option('env-file');
    $envValues = null;

    if (is_string($envFile) && trim($envFile) !== '') {
        $envPath = base_path(trim($envFile));

        if (! is_file($envPath)) {
            $this->error('Env file not found: '.$envPath);

            return SymfonyCommand::FAILURE;
        }

        $envValues = $parseEnvFile($envPath);
        $this->info('Production readiness check');
        $this->line('Source: '.$envPath);
        $this->newLine();
    } else {
        $this->info('Production readiness check');
        $this->newLine();
    }

    $readValue = static function (string $key, mixed $fallback = null) use ($envValues) {
        if (is_array($envValues)) {
            return $envValues[$key] ?? $fallback;
        }

        return $fallback;
    };

    $appEnv = is_array($envValues)
        ? (string) $readValue('APP_ENV', config('app.env', app()->environment()))
        : (string) config('app.env', app()->environment());

    $appDebug = is_array($envValues)
        ? strtolower((string) $readValue('APP_DEBUG', 'true')) === 'true'
        : (bool) config('app.debug');

    $appKey = is_array($envValues)
        ? (string) $readValue('APP_KEY', '')
        : (string) config('app.key');

    $appUrl = is_array($envValues)
        ? (string) $readValue('APP_URL', '')
        : (string) config('app.url');

    $frontendUrl = is_array($envValues)
        ? (string) $readValue('FRONTEND_URL', '')
        : (string) config('scout.frontend_url');

    $corsOrigins = is_array($envValues)
        ? array_values(array_filter(array_map('trim', explode(',', (string) $readValue('CORS_ALLOWED_ORIGINS', '')))))
        : config('scout.cors.allowed_origins', []);

    $dbConnection = is_array($envValues)
        ? (string) $readValue('DB_CONNECTION', '')
        : (string) config('database.default');

    $queueConnection = is_array($envValues)
        ? (string) $readValue('QUEUE_CONNECTION', '')
        : (string) config('queue.default');

    $mailMailer = is_array($envValues)
        ? (string) $readValue('MAIL_MAILER', '')
        : (string) config('mail.default');

    $cacheStore = is_array($envValues)
        ? (string) $readValue('CACHE_STORE', '')
        : (string) config('cache.default');

    $sessionDriver = is_array($envValues)
        ? (string) $readValue('SESSION_DRIVER', '')
        : (string) config('session.driver');

    $logLevel = is_array($envValues)
        ? (string) $readValue('LOG_LEVEL', 'debug')
        : (string) config('logging.channels.stack.level', env('LOG_LEVEL', 'debug'));

    $checks = [
        [
            'label' => 'APP_ENV',
            'value' => $appEnv,
            'severity' => 'critical',
            'valid' => $appEnv === 'production',
            'hint' => 'Set APP_ENV=production before deployment.',
        ],
        [
            'label' => 'APP_DEBUG',
            'value' => $appDebug ? 'true' : 'false',
            'severity' => 'critical',
            'valid' => ! $appDebug,
            'hint' => 'Set APP_DEBUG=false in production.',
        ],
        [
            'label' => 'APP_KEY',
            'value' => $isResolvedValue($appKey) ? 'configured' : 'missing',
            'severity' => 'critical',
            'valid' => $isResolvedValue($appKey),
            'hint' => 'Generate and store a real APP_KEY.',
        ],
        [
            'label' => 'APP_URL',
            'value' => $appUrl,
            'severity' => 'critical',
            'valid' => $isPublicUrl($appUrl),
            'hint' => 'Use a public HTTPS API URL, not localhost.',
        ],
        [
            'label' => 'FRONTEND_URL',
            'value' => $frontendUrl,
            'severity' => 'critical',
            'valid' => $isPublicUrl($frontendUrl),
            'hint' => 'Use the real frontend origin over HTTPS.',
        ],
        [
            'label' => 'CORS_ALLOWED_ORIGINS',
            'value' => implode(', ', $corsOrigins),
            'severity' => 'critical',
            'valid' => $hasOnlyPublicOrigins($corsOrigins),
            'hint' => 'Only include public HTTPS origins in CORS_ALLOWED_ORIGINS.',
        ],
        [
            'label' => 'DB_CONNECTION',
            'value' => $dbConnection,
            'severity' => 'critical',
            'valid' => $dbConnection !== 'sqlite' && $dbConnection !== '',
            'hint' => 'Use MySQL or PostgreSQL in production.',
        ],
        [
            'label' => 'QUEUE_CONNECTION',
            'value' => $queueConnection,
            'severity' => 'critical',
            'valid' => ! in_array($queueConnection, ['sync', 'null', ''], true),
            'hint' => 'Use redis, database, or another real queue backend.',
        ],
        [
            'label' => 'MAIL_MAILER',
            'value' => $mailMailer,
            'severity' => 'critical',
            'valid' => ! in_array($mailMailer, ['log', 'array', ''], true),
            'hint' => 'Use a real SMTP or API mail transport.',
        ],
        [
            'label' => 'CACHE_STORE',
            'value' => $cacheStore,
            'severity' => 'warning',
            'valid' => ! in_array($cacheStore, ['array', 'file', ''], true),
            'hint' => 'Redis or database cache is safer for production traffic.',
        ],
        [
            'label' => 'SESSION_DRIVER',
            'value' => $sessionDriver,
            'severity' => 'warning',
            'valid' => ! in_array($sessionDriver, ['array', 'file', ''], true),
            'hint' => 'Cookie, database, or redis sessions scale better than file/array.',
        ],
        [
            'label' => 'LOG_LEVEL',
            'value' => $logLevel,
            'severity' => 'warning',
            'valid' => strtolower($logLevel) !== 'debug',
            'hint' => 'Use info or warning log level in production.',
        ],
        [
            'label' => 'IYZICO_API_KEY',
            'value' => $isResolvedValue((string) $readValue('IYZICO_API_KEY', env('IYZICO_API_KEY'))) ? 'configured' : 'missing',
            'severity' => 'warning',
            'valid' => $isResolvedValue((string) $readValue('IYZICO_API_KEY', env('IYZICO_API_KEY'))),
            'hint' => 'Required if iyzico payments are enabled.',
        ],
        [
            'label' => 'IYZICO_SECRET_KEY',
            'value' => $isResolvedValue((string) $readValue('IYZICO_SECRET_KEY', env('IYZICO_SECRET_KEY'))) ? 'configured' : 'missing',
            'severity' => 'warning',
            'valid' => $isResolvedValue((string) $readValue('IYZICO_SECRET_KEY', env('IYZICO_SECRET_KEY'))),
            'hint' => 'Required if iyzico payments are enabled.',
        ],
        [
            'label' => 'IYZICO_CALLBACK_URL',
            'value' => (string) $readValue('IYZICO_CALLBACK_URL', env('IYZICO_CALLBACK_URL')),
            'severity' => 'warning',
            'valid' => $isPublicUrl((string) $readValue('IYZICO_CALLBACK_URL', env('IYZICO_CALLBACK_URL'))),
            'hint' => 'Use a public HTTPS callback URL if iyzico payments are enabled.',
        ],
        [
            'label' => 'STRIPE_SECRET',
            'value' => $isResolvedValue((string) $readValue('STRIPE_SECRET', env('STRIPE_SECRET'))) ? 'configured' : 'missing',
            'severity' => 'warning',
            'valid' => $isResolvedValue((string) $readValue('STRIPE_SECRET', env('STRIPE_SECRET'))),
            'hint' => 'Required if Stripe payments are enabled.',
        ],
        [
            'label' => 'STRIPE_WEBHOOK_SECRET',
            'value' => $isResolvedValue((string) $readValue('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET'))) ? 'configured' : 'missing',
            'severity' => 'warning',
            'valid' => $isResolvedValue((string) $readValue('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET'))),
            'hint' => 'Required if Stripe webhooks are enabled.',
        ],
        [
            'label' => 'PAYPAL_CLIENT_ID',
            'value' => $isResolvedValue((string) $readValue('PAYPAL_CLIENT_ID', env('PAYPAL_CLIENT_ID'))) ? 'configured' : 'missing',
            'severity' => 'warning',
            'valid' => $isResolvedValue((string) $readValue('PAYPAL_CLIENT_ID', env('PAYPAL_CLIENT_ID'))),
            'hint' => 'Required if PayPal payments are enabled.',
        ],
        [
            'label' => 'PAYPAL_SECRET',
            'value' => $isResolvedValue((string) $readValue('PAYPAL_SECRET', env('PAYPAL_SECRET'))) ? 'configured' : 'missing',
            'severity' => 'warning',
            'valid' => $isResolvedValue((string) $readValue('PAYPAL_SECRET', env('PAYPAL_SECRET'))),
            'hint' => 'Required if PayPal payments are enabled.',
        ],
    ];

    if (is_array($envValues)) {
        foreach ([
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'REDIS_HOST',
            'REDIS_PORT',
            'MAIL_HOST',
            'MAIL_PORT',
            'MAIL_USERNAME',
            'MAIL_PASSWORD',
        ] as $key) {
            $checks[] = [
                'label' => $key,
                'value' => $isResolvedValue((string) $readValue($key, '')) ? 'configured' : 'missing',
                'severity' => 'warning',
                'valid' => $isResolvedValue((string) $readValue($key, '')),
                'hint' => $key.' still uses a placeholder or is empty in the env file.',
            ];
        }
    }

    $criticalFailures = [];
    $warnings = [];

    foreach ($checks as $check) {
        $status = $check['valid'] ? 'PASS' : strtoupper($check['severity']);
        $line = sprintf('[%s] %s => %s', $status, $check['label'], $check['value'] !== '' ? $check['value'] : 'empty');

        if ($check['valid']) {
            $this->line($line);
            continue;
        }

        $this->warn($line);

        if ($check['severity'] === 'critical') {
            $criticalFailures[] = $check['hint'];
        } else {
            $warnings[] = $check['hint'];
        }
    }

    $this->newLine();

    if ($criticalFailures !== []) {
        $this->error('Critical blockers:');
        foreach ($criticalFailures as $hint) {
            $this->line('- '.$hint);
        }

        if ($warnings !== []) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($warnings as $hint) {
                $this->line('- '.$hint);
            }
        }

        return SymfonyCommand::FAILURE;
    }

    if ($warnings !== []) {
        $this->warn('Warnings:');
        foreach ($warnings as $hint) {
            $this->line('- '.$hint);
        }
        $this->newLine();
    }

    $this->info('Release check passed. Run migrations, cache warmup, queue worker, and smoke tests before go-live.');

    return SymfonyCommand::SUCCESS;
})->purpose('Validate production configuration before release');

Artisan::command('security:revoke-legacy-tokens {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $legacyTokenIds = [];

    DB::table('personal_access_tokens')
        ->select(['id', 'abilities'])
        ->orderBy('id')
        ->chunkById(500, function ($tokens) use (&$legacyTokenIds): void {
            foreach ($tokens as $token) {
                $abilities = json_decode((string) $token->abilities, true);
                if (is_array($abilities) && in_array('*', $abilities, true)) {
                    $legacyTokenIds[] = (int) $token->id;
                }
            }
        });

    $count = count($legacyTokenIds);

    if (! $dryRun && $count > 0) {
        DB::table('personal_access_tokens')->whereIn('id', $legacyTokenIds)->delete();
    }

    $this->info(($dryRun ? 'Dry run: ' : '').$count.' legacy wildcard token bulundu'.($dryRun ? '.' : ' ve iptal edildi.'));
})->purpose('Revoke legacy Sanctum tokens that use wildcard (*) ability');

Schedule::command('security:revoke-legacy-tokens')->dailyAt('03:00');
