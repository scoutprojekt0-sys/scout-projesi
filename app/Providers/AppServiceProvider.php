<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\Contact;
use App\Models\Media;
use App\Models\Opportunity;
use App\Models\Subscription;
use App\Policies\ApplicationPolicy;
use App\Policies\ContactPolicy;
use App\Policies\MediaPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->assertRequiredProductionConfig();

        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email'));
            $key = $email !== '' ? $email.'|'.$request->ip() : $request->ip();
            $limit = (int) config('scout.rate_limits.auth_per_minute', 5);

            return [Limit::perMinute($limit)->by($key)];
        });

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $isRead = in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true);
            $perMinute = $isRead
                ? (int) config('scout.rate_limits.api_read_per_minute', 120)
                : (int) config('scout.rate_limits.api_write_per_minute', 40);

            return [Limit::perMinute($perMinute)->by($key)];
        });
    }

    private function assertRequiredProductionConfig(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $checks = [
            'APP_KEY' => config('app.key'),
            'APP_URL' => config('app.url'),
            'FRONTEND_URL' => config('scout.frontend_url'),
            'CORS_ALLOWED_ORIGINS' => implode(',', config('scout.cors.allowed_origins', [])),
        ];

        $missing = array_keys(array_filter($checks, static function ($value): bool {
            return ! is_string($value) || trim($value) === '';
        }));

        if ($missing !== []) {
            throw new RuntimeException(
                'Missing required production configuration: '.implode(', ', $missing)
            );
        }
    }
}
