<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\VideoClip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class VideoClipController extends Controller
{
    use ApiResponds;

    public function index(int $userId): JsonResponse
    {
        $clips = VideoClip::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);

        $clips->getCollection()->transform(fn (VideoClip $clip) => $this->transformPublicClip($clip));

        return $this->paginatedListResponse($clips, 'Video listesi hazir.');
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
            'video_url'         => ['required_without:file', 'nullable', 'url'],
            'file'              => ['required_without:video_url', 'nullable', 'file', 'max:102400', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm'],
            'thumbnail_url'     => ['nullable', 'url'],
            'platform'          => ['nullable', 'in:youtube,vimeo,custom'],
            'platform_video_id' => ['nullable', 'string', 'max:255'],
            'duration_seconds'  => ['nullable', 'integer', 'min:1'],
            'match_date'        => ['nullable', 'date'],
            'tags'              => ['nullable', 'array'],
            'sport'             => ['nullable', 'string', 'max:40'],
            'ai_dataset_candidate' => ['nullable', 'boolean'],
        ]);

        $videoUrl = $validated['video_url'] ?? null;
        $platform = $validated['platform'] ?? 'custom';

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('videos/'.$request->user()->id, 'public');
            $videoUrl = Storage::disk('public')->url($path);
            $platform = 'custom';
        }

        if (!$videoUrl) {
            return $this->errorResponse('Video dosyasi veya video URL gerekli.', Response::HTTP_UNPROCESSABLE_ENTITY, 'video_source_required');
        }

        $sport = $this->normalizeSport($validated['sport'] ?? null);
        $tags = collect($validated['tags'] ?? [])
            ->map(static fn ($value) => trim((string) $value))
            ->filter(static fn ($value) => $value !== '')
            ->values();

        if ($sport !== null && ! $tags->contains($sport)) {
            $tags->push($sport);
        }

        $metadata = array_filter([
            'sport' => $sport,
            'ai_dataset_candidate' => (bool) ($validated['ai_dataset_candidate'] ?? false),
        ], static fn ($value) => $value !== null);

        return $this->successResponse(
            VideoClip::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'video_url' => $videoUrl,
                'thumbnail_url' => $validated['thumbnail_url'] ?? null,
                'platform' => $platform,
                'platform_video_id' => $validated['platform_video_id'] ?? null,
                'duration_seconds' => $validated['duration_seconds'] ?? null,
                'match_date' => $validated['match_date'] ?? null,
                'tags' => $tags->all() ?: null,
                'metadata' => $metadata ?: null,
            ]),
            'Video eklendi.', Response::HTTP_CREATED
        );
    }

    private function normalizeSport(?string $sport): ?string
    {
        if (! is_string($sport)) {
            return null;
        }

        $normalized = strtolower(trim($sport));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'futbol', 'football', 'soccer' => 'football',
            'basketbol', 'basketball' => 'basketball',
            'voleybol', 'volleyball' => 'volleyball',
            default => $normalized,
        };
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

    private function transformPublicClip(VideoClip $clip): array
    {
        return [
            'id' => (int) $clip->id,
            'user_id' => (int) $clip->user_id,
            'title' => (string) $clip->title,
            'description' => $clip->description,
            'video_url' => (string) $clip->video_url,
            'thumbnail_url' => $clip->thumbnail_url,
            'platform' => $clip->platform,
            'duration_seconds' => $clip->duration_seconds,
            'match_date' => optional($clip->match_date)?->toDateString(),
            'tags' => is_array($clip->tags) ? $clip->tags : [],
            'created_at' => optional($clip->created_at)?->toIso8601String(),
        ];
    }
}
