<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequestMetricsLogger
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);
        $slowThresholdMs = (int) config('scout.monitoring.slow_request_ms', 800);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::channel('ops')->error('request.exception', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'ip' => (string) $request->ip(),
                'user_id' => $request->user()?->id,
                'duration_ms' => $durationMs,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->getStatusCode();
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'ip' => (string) $request->ip(),
            'user_id' => $request->user()?->id,
            'status' => $status,
            'duration_ms' => $durationMs,
        ];

        Log::channel('ops')->info('request.summary', $context);

        // Lightweight daily counters for admin operations/rate-limit visibility.
        $dayKey = now()->format('Ymd');
        Cache::increment("ops:requests:{$dayKey}:total");
        Cache::increment("ops:requests:{$dayKey}:status:{$status}");
        if ($status === 429) {
            Cache::increment("ops:requests:{$dayKey}:rate_limited");
        }
        if ($status >= 500) {
            Cache::increment("ops:requests:{$dayKey}:server_error");
        }

        if ($status >= 500 || $durationMs >= $slowThresholdMs) {
            Log::channel('ops')->warning('request.alert', $context + [
                'slow_threshold_ms' => $slowThresholdMs,
            ]);
        }

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
