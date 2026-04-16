<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiRootController extends Controller
{
    use ApiResponds;

    public function __invoke(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'ok',
            'service' => 'Scout API',
            'version' => app()->version(),
            'health_url' => url('/up'),
            'ping_url' => url('/api/ping'),
        ], 'Scout API hazir.');
    }
}
