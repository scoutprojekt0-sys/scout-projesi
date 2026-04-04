<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalToolAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $this->isPrivilegedUser($user)) {
            return $next($request);
        }

        $ip = (string) ($request->ip() ?? '');
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return $next($request);
        }

        abort(403, 'Bu arac sadece ic kullanim icindir.');
    }

    private function isPrivilegedUser(object $user): bool
    {
        $role = strtolower((string) ($user->role ?? ''));

        return in_array($role, ['admin', 'staff'], true);
    }
}
