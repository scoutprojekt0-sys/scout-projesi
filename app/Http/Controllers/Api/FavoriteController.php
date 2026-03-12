<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->with('targetUser:id,name,email,role,city,position,photo_url')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $favorites,
        ]);
    }

    public function toggle(Request $request, int $targetUserId): JsonResponse
    {
        $userId = (int) $request->user()->id;

        if ($userId === $targetUserId) {
            return response()->json([
                'ok' => false,
                'message' => 'Kendinizi favorilere ekleyemezsiniz.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $favorite = Favorite::query()
            ->where('user_id', $userId)
            ->where('target_user_id', $targetUserId)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Favorilerden cikarildi.',
                'data' => ['is_favorited' => false],
            ]);
        }

        Favorite::query()->create([
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Favorilere eklendi.',
            'data' => ['is_favorited' => true],
        ]);
    }

    public function check(Request $request, int $targetUserId): JsonResponse
    {
        $isFavorited = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('target_user_id', $targetUserId)
            ->exists();

        return response()->json([
            'ok' => true,
            'data' => ['is_favorited' => $isFavorited],
        ]);
    }
}
