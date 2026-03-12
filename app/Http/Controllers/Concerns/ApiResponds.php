<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponds
{
    protected function successResponse(mixed $data = null, string $message = 'Islem basarili.', int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'ok' => true,
            'message' => $message,
            'data' => $data,
        ], $extra), $status);
    }

    protected function errorResponse(string $message = 'Bir hata olustu.', int $status = 400, string $code = 'error', array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], $extra), $status);
    }

    protected function paginatedListResponse(LengthAwarePaginator $paginator, string $message = 'Liste hazir.'): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ]);
    }
}
