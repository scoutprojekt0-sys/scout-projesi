<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ProfileView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileViewController extends Controller
{
    use ApiResponds;

    public function track(Request $request, int $userId): JsonResponse
    {
        $viewerUserId = $request->user()?->id;

        if ($viewerUserId === $userId) {
            return $this->successResponse(null, 'Goruntuleme kaydedilmedi (kendi profil).');
        }

        ProfileView::query()->create([
            'viewer_user_id' => $viewerUserId,
            'viewed_user_id' => $userId,
            'ip_address'     => $request->ip(),
            'viewed_at'      => now(),
        ]);

        return $this->successResponse(null, 'Goruntuleme kaydedildi.');
    }

    public function myViews(Request $request): JsonResponse
    {
        $views = ProfileView::query()
            ->where('viewed_user_id', $request->user()->id)
            ->with(['viewer:id,name,email,role'])
            ->latest('viewed_at')
            ->paginate(30);

        return $this->successResponse($views, 'Profil goruntuleme gecmisi hazir.');
    }

    public function viewCount(Request $request, int $userId): JsonResponse
    {
        return $this->successResponse([
            'view_count' => ProfileView::query()->where('viewed_user_id', $userId)->count(),
        ], 'Goruntuleme sayisi hazir.');
    }
}
