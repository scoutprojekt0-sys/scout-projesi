<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse { return response()->json(['ok' => true, 'message' => 'TODO: upload media']); }
    public function indexByUser(int $id): JsonResponse { return response()->json(['ok' => true, 'message' => 'TODO: user media', 'id' => $id]); }
    public function destroy(int $id): JsonResponse { return response()->json(['ok' => true, 'message' => 'TODO: delete media', 'id' => $id]); }
}
