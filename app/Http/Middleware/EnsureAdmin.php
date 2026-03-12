<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'code' => 'unauthenticated',
                'message' => 'Bu islem icin giris yapmaniz gerekiyor.',
            ], 401);
        }

        $role = strtolower((string) ($user->role ?? ''));
        $editorRole = strtolower((string) ($user->editor_role ?? ''));

        if ($role !== 'admin' && $editorRole !== 'admin') {
            return response()->json([
                'ok' => false,
                'code' => 'forbidden_admin_only',
                'message' => 'Bu endpoint sadece admin kullanicilar icindir.',
            ], 403);
        }

        return $next($request);
    }
}
