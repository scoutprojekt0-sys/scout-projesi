<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\VideoClip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VideoClipController extends Controller
{
    use ApiResponds;

    public function index(int $userId): JsonResponse
    {
        return $this->paginatedListResponse(
            VideoClip::where('user_id', $userId)->orderByDesc('created_at')->paginate(20),
            'Video listesi hazir.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $clip = VideoClip::find($id);
        if (! $clip) {
            return $this->errorResponse('Video bulunamadi.', Response::HTTP_NOT_FOUND, 'video_not_found');
        }
        $clip->increment('view_count');
        return $this->successResponse($clip->fresh(), 'Video detayi hazir.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'video_url'         => ['required', 'url'],
            'thumbnail_url'     => ['nullable', 'url'],
            'platform'          => ['required', 'in:youtube,vimeo,custom'],
            'platform_video_id' => ['nullable', 'string', 'max:255'],
            'duration_seconds'  => ['nullable', 'integer', 'min:1'],
            'match_date'        => ['nullable', 'date'],
            'tags'              => ['nullable', 'array'],
        ]);

        return $this->successResponse(
            VideoClip::create(['user_id' => $request->user()->id, ...$validated]),
            'Video eklendi.', Response::HTTP_CREATED
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $clip = VideoClip::where('user_id', $request->user()->id)->find($id);
        if (! $clip) {
            return $this->errorResponse('Video bulunamadi.', Response::HTTP_NOT_FOUND, 'video_not_found');
        }
        $clip->delete();
        return $this->successResponse(null, 'Video silindi.');
    }

    public function trending(): JsonResponse
    {
        return $this->successResponse(
            VideoClip::orderByDesc('view_count')->limit(50)->get(),
            'Trend videolar hazir.'
        );
    }

    public function byTag(string $tag): JsonResponse
    {
        $clips = VideoClip::where('tags', 'like', '%"' . $tag . '"%')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['ok' => true, 'data' => $clips]);
    }
}
