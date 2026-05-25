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
        'admin',
        'admin-dashboard.html',
        'admin-dashboard-improved.html',
        'admin-dashboard-modern.html',
        'admin-dashboard-new.html',
        'api/ping',
        'api/auth/login',
        'api/auth/register',
        'api/auth/verify-email',
        'api/auth/resend-verification',
        'api/auth/password/forgot',
        'api/auth/password/reset',
        'api/auth/supabase/exchange',
        'api/users',
        'api/users/*',
        'api/transfers',
        'api/transfers/*',
        'api/notifications/count',
        'api/analytics/admin-overview',
        'api/admin/*',
        'api/ai-dataset-candidates',
        'api/ai-dataset-candidates/*',
        'api/ai-training-runs',
        'api/ai-training-runs/*',
        'api/ai-models/active',
        'api/ai-models/rollouts',
        'api/ai-models/publish',
        'api/ai-models/rollback',
        'api/data-quality/*',
    ];
}
