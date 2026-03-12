<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RejectLegacyWildcardToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->can('*')) {
            $token->delete();

            Log::channel('security')->warning('Legacy wildcard token rejected', [
                'user_id' => $request->user()?->id,
                'token_id' => $token->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Guvenlik guncellemesi nedeniyle tekrar giris yapin.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
