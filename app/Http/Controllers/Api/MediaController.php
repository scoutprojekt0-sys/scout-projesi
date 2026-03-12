<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    use ApiResponds;

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $type = str_starts_with($mime, 'image/') ? 'image' : 'video';

        $path  = $file->store('media/'.$request->user()->id, 'public');
        $media = Media::query()->create([
            'user_id'  => (int) $request->user()->id,
            'type'     => $type,
            'url'      => Storage::disk('public')->url($path),
            'thumb_url'=> null,
            'title'    => $request->validated('title'),
        ]);

        return $this->successResponse($media, 'Media yuklendi.', Response::HTTP_CREATED);
    }

    public function indexByUser(int $id): JsonResponse
    {
        $validated = request()->validate([
            'type'     => ['nullable', 'in:image,video'],
            'sort_by'  => ['nullable', 'in:created_at,title,type'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Media::query()->where('user_id', $id);
        if (!empty($validated['type'])) { $query->where('type', $validated['type']); }

        $sortBy  = $validated['sort_by']  ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $media = $query->orderBy($sortBy, $sortDir)->paginate((int) ($validated['per_page'] ?? 20));

        return $this->successResponse($media, 'Medya listesi hazir.', 200, [
            'filters' => ['type' => $validated['type'] ?? null, 'sort_by' => $sortBy, 'sort_dir' => $sortDir],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $media = Media::query()->find($id);

        if (!$media) {
            return $this->errorResponse('Media bulunamadi.', Response::HTTP_NOT_FOUND, 'media_not_found');
        }

        $this->authorize('delete', $media);

        $path = preg_replace('#^/storage/#', '', parse_url($media->url, PHP_URL_PATH) ?? '');
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }

        $media->delete();

        return $this->successResponse(null, 'Media silindi.');
    }
}
