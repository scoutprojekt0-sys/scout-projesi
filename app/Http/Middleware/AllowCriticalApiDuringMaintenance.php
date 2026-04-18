<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

class AllowCriticalApiDuringMaintenance extends PreventRequestsDuringMaintenance
{
    /**
     * Keep health checks and auth endpoints reachable during maintenance.
     *
     * @var array<int, string>
     */
    protected $except = [
        'up',
        'api/ping',
        'api/auth/login',
        'api/auth/register',
        'api/auth/verify-email',
        'api/auth/resend-verification',
        'api/auth/password/forgot',
        'api/auth/password/reset',
        'api/auth/supabase/exchange',
    ];
}
