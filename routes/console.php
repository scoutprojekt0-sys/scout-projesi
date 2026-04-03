<?php

use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

Artisan::command('ai:export-video-candidates {sport=all} {--only-public : Export only public player videos}', function (string $sport) {
    $normalizeSport = static function (?array $tags, ?array $metadata): string {
        $map = [
            'futbol' => 'football',
            'football' => 'football',
            'soccer' => 'football',
            'basketbol' => 'basketball',
            'basketball' => 'basketball',
            'voleybol' => 'volleyball',
            'volleyball' => 'volleyball',
        ];

        foreach (array_merge($tags ?? [], array_values($metadata ?? [])) as $value) {
            $normalized = strtolower(trim((string) $value));
            if (isset($map[$normalized])) {
                return $map[$normalized];
            }
        }

        return 'football';
    };

    $requestedSport = strtolower(trim($sport));
    $allowedSports = ['all', 'football', 'basketball', 'volleyball'];
    if (! in_array($requestedSport, $allowedSports, true)) {
        $this->error('Desteklenmeyen spor: '.$sport);

        return SymfonyCommand::FAILURE;
    }

    $query = VideoClip::query()->with('player')->orderBy('id');
    if ($this->option('only-public')) {
        $query->whereHas('player', static function ($builder) {
            $builder->where('role', 'player')->where('is_public', true);
        });
    }

    $clips = $query->get()->filter(function (VideoClip $clip) use ($requestedSport, $normalizeSport) {
        $sportName = $normalizeSport($clip->tags, $clip->metadata);
        if (! (bool) ($clip->metadata['ai_dataset_candidate'] ?? false)) {
            return false;
        }
        return $requestedSport === 'all' || $sportName === $requestedSport;
    })->values();

    $manifestDir = base_path('raw_videos/manifests');
    if (! File::exists($manifestDir)) {
        File::makeDirectory($manifestDir, 0777, true);
    }

    $suffix = $requestedSport === 'all' ? 'all' : $requestedSport;
    $manifestPath = $manifestDir.'/video_candidates_'.$suffix.'.csv';
    $handle = fopen($manifestPath, 'w');
    if ($handle === false) {
        $this->error('Manifest dosyasi olusturulamadi: '.$manifestPath);

        return SymfonyCommand::FAILURE;
    }

    fputcsv($handle, [
        'video_clip_id',
        'player_id',
        'player_name',
        'player_email',
        'player_city',
        'sport',
        'title',
        'video_url',
        'platform',
        'duration_seconds',
        'match_date',
        'is_public',
        'ai_dataset_candidate',
        'tags',
    ]);

    foreach ($clips as $clip) {
        $sportName = $normalizeSport($clip->tags, $clip->metadata);
        fputcsv($handle, [
            $clip->id,
            $clip->player?->id,
            $clip->player?->name,
            $clip->player?->email,
            $clip->player?->city,
            $sportName,
            $clip->title,
            $clip->video_url,
            $clip->platform,
            $clip->duration_seconds,
            optional($clip->match_date)?->format('Y-m-d'),
            $clip->player?->is_public ? 'yes' : 'no',
            ! empty($clip->metadata['ai_dataset_candidate']) ? 'yes' : 'no',
            implode('|', $clip->tags ?? []),
        ]);
    }

    fclose($handle);

    $this->info('Video aday manifesti hazir.');
    $this->line('Kayit sayisi: '.$clips->count());
    $this->line('Dosya: '.$manifestPath);

    return SymfonyCommand::SUCCESS;
})->purpose('Export uploaded video clips as AI dataset candidate manifest');

Artisan::command('ai:sync-video-candidates {sport=all} {--only-public : Sync only public player videos} {--limit=0 : Limit clip count}', function (string $sport) {
    $normalizeSport = static function (?array $tags, ?array $metadata): string {
        $map = [
            'futbol' => 'football',
            'football' => 'football',
            'soccer' => 'football',
            'basketbol' => 'basketball',
            'basketball' => 'basketball',
            'voleybol' => 'volleyball',
            'volleyball' => 'volleyball',
        ];

        foreach (array_merge($tags ?? [], array_values($metadata ?? [])) as $value) {
            $normalized = strtolower(trim((string) $value));
            if (isset($map[$normalized])) {
                return $map[$normalized];
            }
        }

        return 'football';
    };

    $requestedSport = strtolower(trim($sport));
    $allowedSports = ['all', 'football', 'basketball', 'volleyball'];
    if (! in_array($requestedSport, $allowedSports, true)) {
        $this->error('Desteklenmeyen spor: '.$sport);

        return SymfonyCommand::FAILURE;
    }

    $limit = max(0, (int) $this->option('limit'));
    $query = VideoClip::query()->with('player')->orderBy('id');
    if ($this->option('only-public')) {
        $query->whereHas('player', static function ($builder) {
            $builder->where('role', 'player')->where('is_public', true);
        });
    }

    $clips = $query->get()->filter(function (VideoClip $clip) use ($requestedSport, $normalizeSport) {
        $sportName = $normalizeSport($clip->tags, $clip->metadata);
        if (! (bool) ($clip->metadata['ai_dataset_candidate'] ?? false)) {
            return false;
        }

        return $requestedSport === 'all' || $sportName === $requestedSport;
    })->values();

    if ($limit > 0) {
        $clips = $clips->take($limit)->values();
    }

    $saved = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($clips as $clip) {
        $sportName = $normalizeSport($clip->tags, $clip->metadata);
        $targetDir = base_path('raw_videos/'.$sportName);
        if (! File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0777, true);
        }

        $extension = pathinfo(parse_url((string) $clip->video_url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $extension = $extension !== '' ? strtolower($extension) : 'mp4';
        $safeTitle = Str::slug((string) $clip->title, '_');
        $targetPath = $targetDir.'/clip_'.$clip->id.'_'.$safeTitle.'.'.$extension;

        if (File::exists($targetPath)) {
            $this->line("SKIP {$clip->id} -> zaten var");
            $skipped++;
            continue;
        }

        try {
            $videoUrl = (string) $clip->video_url;
            if (str_starts_with($videoUrl, 'http://') || str_starts_with($videoUrl, 'https://')) {
                $response = Http::timeout(90)->withOptions(['stream' => true])->get($videoUrl);
                if (! $response->successful()) {
                    throw new RuntimeException('download basarisiz: '.$response->status());
                }
                File::put($targetPath, $response->body());
            } elseif (File::exists($videoUrl)) {
                File::copy($videoUrl, $targetPath);
            } else {
                throw new RuntimeException('desteklenmeyen veya bulunamayan kaynak');
            }

            $saved++;
            $this->info("OK {$clip->id} -> {$targetPath}");
        } catch (\Throwable $exception) {
            $failed++;
            $this->warn("FAIL {$clip->id} -> {$exception->getMessage()}");
        }
    }

    $this->newLine();
    $this->info("Tamamlandi. saved={$saved} skipped={$skipped} failed={$failed}");

    return $failed > 0 ? SymfonyCommand::FAILURE : SymfonyCommand::SUCCESS;
})->purpose('Download AI dataset candidate videos into raw_videos folders');

Artisan::command('ai:prepare-dataset {sport} {--limit=0 : Limit clip count} {--only-public : Sync only public player videos} {--sample-every-seconds=1 : Frame sample interval} {--max-seconds=180 : Max seconds per video}', function (string $sport) {
    $requestedSport = strtolower(trim($sport));
    $allowedSports = ['football', 'basketball', 'volleyball'];
    if (! in_array($requestedSport, $allowedSports, true)) {
        $this->error('Desteklenmeyen spor: '.$sport);

        return SymfonyCommand::FAILURE;
    }

    $this->info("1/2 Sync basliyor: {$requestedSport}");

    $syncExit = Artisan::call('ai:sync-video-candidates', [
        'sport' => $requestedSport,
        '--only-public' => (bool) $this->option('only-public'),
        '--limit' => (int) $this->option('limit'),
    ]);
    $this->output->write(Artisan::output());

    if ($syncExit !== SymfonyCommand::SUCCESS) {
        $this->error('Sync adimi basarisiz oldu.');

        return SymfonyCommand::FAILURE;
    }

    $sourceDir = base_path('raw_videos/'.$requestedSport);
    $datasetDir = base_path('ai-worker/datasets/'.$requestedSport);
    $scriptPath = base_path('ai-worker/scripts/prepare_football_dataset.py');
    $pythonPath = base_path('ai-worker/.venv/Scripts/python.exe');

    if (! File::exists($pythonPath)) {
        $this->error('Python sanal ortam bulunamadi: '.$pythonPath);

        return SymfonyCommand::FAILURE;
    }

    if (! File::exists($scriptPath)) {
        $this->error('Dataset prep script bulunamadi: '.$scriptPath);

        return SymfonyCommand::FAILURE;
    }

    $this->newLine();
    $this->info("2/2 Dataset prep basliyor: {$requestedSport}");

    $command = sprintf(
        '"%s" "%s" --source-dir "%s" --output-dir "%s" --sample-every-seconds=%s --max-seconds=%s',
        $pythonPath,
        $scriptPath,
        $sourceDir,
        $datasetDir,
        (string) $this->option('sample-every-seconds'),
        (string) $this->option('max-seconds'),
    );

    passthru($command, $prepExit);

    if ((int) $prepExit !== 0) {
        $this->error('Dataset prep adimi basarisiz oldu.');

        return SymfonyCommand::FAILURE;
    }

    $this->newLine();
    $this->info('Dataset hazirlama tamamlandi.');
    $this->line('Raw video kaynagi: '.$sourceDir);
    $this->line('Dataset klasoru: '.$datasetDir);

    return SymfonyCommand::SUCCESS;
})->purpose('Sync AI candidate videos and prepare dataset frames for a sport');

Artisan::command('ai:dataset-stats {sport}', function (string $sport) {
    $requestedSport = strtolower(trim($sport));
    $allowedSports = ['football', 'basketball', 'volleyball'];
    if (! in_array($requestedSport, $allowedSports, true)) {
        $this->error('Desteklenmeyen spor: '.$sport);

        return SymfonyCommand::FAILURE;
    }

    $datasetDir = base_path('ai-worker/datasets/'.$requestedSport);
    if (! File::exists($datasetDir)) {
        $this->error('Dataset klasoru bulunamadi: '.$datasetDir);

        return SymfonyCommand::FAILURE;
    }

    $summary = [];
    $totalImages = 0;
    $totalLabels = 0;
    $totalAnnotated = 0;

    foreach (['train', 'val', 'test'] as $split) {
        $imageDir = $datasetDir.'/images/'.$split;
        $labelDir = $datasetDir.'/labels/'.$split;

        $images = File::exists($imageDir) ? collect(File::files($imageDir)) : collect();
        $labels = File::exists($labelDir) ? collect(File::files($labelDir)) : collect();
        $annotated = $labels->filter(static function ($file) {
            return trim((string) File::get($file->getPathname())) !== '';
        });

        $imageCount = $images->count();
        $labelCount = $labels->count();
        $annotatedCount = $annotated->count();

        $summary[$split] = [
            'images' => $imageCount,
            'labels' => $labelCount,
            'annotated' => $annotatedCount,
            'empty_labels' => max(0, $labelCount - $annotatedCount),
        ];

        $totalImages += $imageCount;
        $totalLabels += $labelCount;
        $totalAnnotated += $annotatedCount;
    }

    $manifestPath = $datasetDir.'/manifest.csv';
    $manifestExists = File::exists($manifestPath);
    $completion = $totalLabels > 0 ? round(($totalAnnotated / $totalLabels) * 100, 1) : 0.0;

    $this->info('Dataset istatistikleri');
    $this->line('Spor: '.$requestedSport);
    $this->line('Klasor: '.$datasetDir);
    $this->line('Manifest: '.($manifestExists ? 'var' : 'yok'));
    $this->newLine();

    foreach ($summary as $split => $stats) {
        $this->line(strtoupper($split));
        $this->line(' - images: '.$stats['images']);
        $this->line(' - labels: '.$stats['labels']);
        $this->line(' - annotated: '.$stats['annotated']);
        $this->line(' - empty_labels: '.$stats['empty_labels']);
    }

    $this->newLine();
    $this->info('Toplam');
    $this->line('images='.$totalImages);
    $this->line('labels='.$totalLabels);
    $this->line('annotated='.$totalAnnotated);
    $this->line('label_completion='.$completion.'%');

    return SymfonyCommand::SUCCESS;
})->purpose('Show dataset image/label progress for a sport');

Artisan::command('ai:dataset-label-queue {sport} {--split=all : train, val, test or all}', function (string $sport) {
    $requestedSport = strtolower(trim($sport));
    $allowedSports = ['football', 'basketball', 'volleyball'];
    if (! in_array($requestedSport, $allowedSports, true)) {
        $this->error('Desteklenmeyen spor: '.$sport);

        return SymfonyCommand::FAILURE;
    }

    $requestedSplit = strtolower(trim((string) $this->option('split')));
    $allowedSplits = ['all', 'train', 'val', 'test'];
    if (! in_array($requestedSplit, $allowedSplits, true)) {
        $this->error('Desteklenmeyen split: '.$requestedSplit);

        return SymfonyCommand::FAILURE;
    }

    $datasetDir = base_path('ai-worker/datasets/'.$requestedSport);
    if (! File::exists($datasetDir)) {
        $this->error('Dataset klasoru bulunamadi: '.$datasetDir);

        return SymfonyCommand::FAILURE;
    }

    $splits = $requestedSplit === 'all' ? ['train', 'val', 'test'] : [$requestedSplit];
    $queueDir = $datasetDir.'/queues';
    if (! File::exists($queueDir)) {
        File::makeDirectory($queueDir, 0777, true);
    }

    $suffix = $requestedSplit === 'all' ? 'all' : $requestedSplit;
    $queuePath = $queueDir.'/label_queue_'.$suffix.'.csv';
    $handle = fopen($queuePath, 'w');
    if ($handle === false) {
        $this->error('Queue dosyasi olusturulamadi: '.$queuePath);

        return SymfonyCommand::FAILURE;
    }

    fputcsv($handle, ['split', 'image_path', 'label_path', 'status']);

    $count = 0;
    foreach ($splits as $split) {
        $imageDir = $datasetDir.'/images/'.$split;
        $labelDir = $datasetDir.'/labels/'.$split;
        if (! File::exists($imageDir) || ! File::exists($labelDir)) {
            continue;
        }

        foreach (File::files($imageDir) as $imageFile) {
            $labelPath = $labelDir.'/'.$imageFile->getFilenameWithoutExtension().'.txt';
            $status = 'missing';
            if (File::exists($labelPath)) {
                $status = trim((string) File::get($labelPath)) === '' ? 'empty' : 'annotated';
            }

            if ($status === 'annotated') {
                continue;
            }

            fputcsv($handle, [$split, $imageFile->getPathname(), $labelPath, $status]);
            $count++;
        }
    }

    fclose($handle);

    $this->info('Label queue hazir.');
    $this->line('Kayit sayisi: '.$count);
    $this->line('Dosya: '.$queuePath);

    return SymfonyCommand::SUCCESS;
})->purpose('Export unlabeled dataset frames into a labeling queue CSV');

Artisan::command('ai:training-readiness {sport} {--min-images=50 : Minimum total image count} {--min-annotated=30 : Minimum annotated frame count} {--min-completion=60 : Minimum label completion percentage}', function (string $sport) {
    $requestedSport = strtolower(trim($sport));
    $allowedSports = ['football', 'basketball', 'volleyball'];
    if (! in_array($requestedSport, $allowedSports, true)) {
        $this->error('Desteklenmeyen spor: '.$sport);

        return SymfonyCommand::FAILURE;
    }

    $datasetDir = base_path('ai-worker/datasets/'.$requestedSport);
    if (! File::exists($datasetDir)) {
        $this->error('Dataset klasoru bulunamadi: '.$datasetDir);

        return SymfonyCommand::FAILURE;
    }

    $summary = [];
    $totalImages = 0;
    $totalLabels = 0;
    $totalAnnotated = 0;

    foreach (['train', 'val', 'test'] as $split) {
        $imageDir = $datasetDir.'/images/'.$split;
        $labelDir = $datasetDir.'/labels/'.$split;

        $images = File::exists($imageDir) ? collect(File::files($imageDir)) : collect();
        $labels = File::exists($labelDir) ? collect(File::files($labelDir)) : collect();
        $annotated = $labels->filter(static function ($file) {
            return trim((string) File::get($file->getPathname())) !== '';
        });

        $summary[$split] = [
            'images' => $images->count(),
            'labels' => $labels->count(),
            'annotated' => $annotated->count(),
        ];

        $totalImages += $images->count();
        $totalLabels += $labels->count();
        $totalAnnotated += $annotated->count();
    }

    $completion = $totalLabels > 0 ? round(($totalAnnotated / $totalLabels) * 100, 1) : 0.0;
    $minImages = max(1, (int) $this->option('min-images'));
    $minAnnotated = max(1, (int) $this->option('min-annotated'));
    $minCompletion = max(1, (float) $this->option('min-completion'));

    $checks = [
        [
            'label' => 'Toplam image',
            'valid' => $totalImages >= $minImages,
            'value' => "{$totalImages} / {$minImages}",
            'hint' => 'Daha fazla frame uret veya daha fazla video sync et.',
        ],
        [
            'label' => 'Annotated frame',
            'valid' => $totalAnnotated >= $minAnnotated,
            'value' => "{$totalAnnotated} / {$minAnnotated}",
            'hint' => 'Etiketlenmis frame sayisini arttir.',
        ],
        [
            'label' => 'Label completion',
            'valid' => $completion >= $minCompletion,
            'value' => "{$completion}% / {$minCompletion}%",
            'hint' => 'Bos label dosyalarini doldur.',
        ],
        [
            'label' => 'Train split',
            'valid' => $summary['train']['images'] > 0,
            'value' => (string) $summary['train']['images'],
            'hint' => 'Train split bos. Dataset prep tekrar calistir.',
        ],
        [
            'label' => 'Val split',
            'valid' => $summary['val']['images'] > 0 || $totalImages < 10,
            'value' => (string) $summary['val']['images'],
            'hint' => 'Validation split bos. Daha fazla frame gerekebilir.',
        ],
    ];

    $failed = collect($checks)->filter(static fn ($check) => ! $check['valid'])->values();

    $this->info('Training readiness');
    $this->line('Spor: '.$requestedSport);
    $this->newLine();

    foreach ($checks as $check) {
        $status = $check['valid'] ? 'PASS' : 'FAIL';
        $this->line("[{$status}] {$check['label']} => {$check['value']}");
    }

    $this->newLine();
    if ($failed->isEmpty()) {
        $this->info('Dataset train icin hazir gorunuyor.');

        return SymfonyCommand::SUCCESS;
    }

    $this->warn('Eksikler:');
    foreach ($failed as $check) {
        $this->line('- '.$check['hint']);
    }

    return SymfonyCommand::FAILURE;
})->purpose('Check whether a sport dataset is ready for model training');

Artisan::command('ai:train-model {sport} {--device=cpu : Training device, e.g. cpu or 0} {--epochs=60 : Training epochs} {--imgsz=960 : Image size} {--batch=8 : Batch size} {--force : Skip readiness gate}', function (string $sport) {
    $requestedSport = strtolower(trim($sport));
    $allowedSports = ['football', 'basketball', 'volleyball'];
    if (! in_array($requestedSport, $allowedSports, true)) {
        $this->error('Desteklenmeyen spor: '.$sport);

        return SymfonyCommand::FAILURE;
    }

    if (! (bool) $this->option('force')) {
        $this->info('Readiness kontrolu calisiyor...');
        $readinessExit = Artisan::call('ai:training-readiness', ['sport' => $requestedSport]);
        $this->output->write(Artisan::output());

        if ($readinessExit !== SymfonyCommand::SUCCESS) {
            $this->error('Dataset train icin hazir degil. Zorla gecmek icin --force kullan.');

            return SymfonyCommand::FAILURE;
        }
    }

    $pythonPath = base_path('ai-worker/.venv/Scripts/python.exe');
    if (! File::exists($pythonPath)) {
        $this->error('Python sanal ortam bulunamadi: '.$pythonPath);

        return SymfonyCommand::FAILURE;
    }

    $scriptMap = [
        'football' => base_path('ai-worker/scripts/train_football_model.py'),
        'basketball' => base_path('ai-worker/scripts/train_basketball_model.py'),
        'volleyball' => base_path('ai-worker/scripts/train_volleyball_model.py'),
    ];
    $dataMap = [
        'football' => base_path('ai-worker/datasets/football_detection.yaml'),
        'basketball' => base_path('ai-worker/datasets/basketball_detection.yaml'),
        'volleyball' => base_path('ai-worker/datasets/volleyball_detection.yaml'),
    ];

    $scriptPath = $scriptMap[$requestedSport];
    $dataPath = $dataMap[$requestedSport];
    if (! File::exists($scriptPath)) {
        $this->error('Train script bulunamadi: '.$scriptPath);

        return SymfonyCommand::FAILURE;
    }
    if (! File::exists($dataPath)) {
        $this->error('Dataset yaml bulunamadi: '.$dataPath);

        return SymfonyCommand::FAILURE;
    }

    $command = sprintf(
        '"%s" "%s" --data "%s" --device %s --epochs %s --imgsz %s --batch %s',
        $pythonPath,
        $scriptPath,
        $dataPath,
        escapeshellarg((string) $this->option('device')),
        escapeshellarg((string) $this->option('epochs')),
        escapeshellarg((string) $this->option('imgsz')),
        escapeshellarg((string) $this->option('batch')),
    );

    $this->info('Training baslatiliyor...');
    $this->line('Sport: '.$requestedSport);
    $this->line('Data: '.$dataPath);
    $this->line('Device: '.(string) $this->option('device'));

    passthru($command, $trainExit);

    if ((int) $trainExit !== 0) {
        $this->error('Training basarisiz oldu.');

        return SymfonyCommand::FAILURE;
    }

    $this->info('Training tamamlandi.');

    return SymfonyCommand::SUCCESS;
})->purpose('Run sport-specific YOLO training with readiness guard');
