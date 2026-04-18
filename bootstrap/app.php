<?php

use App\Http\Middleware\RejectLegacyWildcardToken;
use App\Http\Middleware\EnsureInternalToolAccess;
use App\Http\Middleware\RequestMetricsLogger;
use App\Http\Middleware\AllowCriticalApiDuringMaintenance;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(
            PreventRequestsDuringMaintenance::class,
            AllowCriticalApiDuringMaintenance::class
        );

        $middleware->append(RequestMetricsLogger::class);
        $middleware->append(\App\Http\Middleware\SetLocale::class);

        // API rate limiting
        $middleware->api(prepend: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':'.env('RATE_LIMIT_API', 60).',1',
        ]);

        // Apply input sanitization to all API routes
        $middleware->api(append: [
            \App\Http\Middleware\SanitizeInput::class,
        ]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'reject_legacy_token' => RejectLegacyWildcardToken::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'internal_tool' => EnsureInternalToolAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tüm API isteklerinde JSON hata yanıtı döndür
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // web route'lara dokunma
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'validation_error',
                    'message' => 'Doğrulama hatası.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'unauthenticated',
                    'message' => 'Bu işlem için giriş yapmanız gerekiyor.',
                ], 401);
            }

            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'forbidden',
                    'message' => 'Bu işlem için yetkiniz yok.',
                ], 403);
            }

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'not_found',
                    'message' => 'Kayıt bulunamadı.',
                ], 404);
            }

            if ($e instanceof HttpException) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'http_error',
                    'message' => $e->getMessage() ?: 'Bir hata oluştu.',
                ], $e->getStatusCode());
            }

            // Production'da internal hata detayı gösterme
            $debug = config('app.debug', false);
            return response()->json([
                'ok'      => false,
                'code'    => 'server_error',
                'message' => $debug ? $e->getMessage() : 'Sunucu hatası. Lütfen tekrar deneyin.',
            ], 500);
        });
    })->create();
