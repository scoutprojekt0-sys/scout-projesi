<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Concerns\ResolvesPublicFileUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    use ApiResponds;
    use ResolvesPublicFileUrls;

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

        return $this->successResponse($this->transformMedia($media), 'Media yuklendi.', Response::HTTP_CREATED);
    }

    public function guestStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/x-msvideo,video/webm'],
            'title' => ['nullable', 'string', 'max:160'],
        ]);

        $guestUser = $this->resolveGuestUploader();
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $type = str_starts_with($mime, 'image/') ? 'image' : 'video';

        $path  = $file->store('media/'.$guestUser->id, 'public');
        $media = Media::query()->create([
            'user_id' => (int) $guestUser->id,
            'type' => $type,
            'url' => Storage::disk('public')->url($path),
            'thumb_url' => null,
            'title' => $validated['title'] ?? null,
        ]);

        return $this->successResponse($this->transformMedia($media), 'Misafir medya yuklendi.', Response::HTTP_CREATED);
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
        $media->getCollection()->transform(fn (Media $item) => $this->transformMedia($item));

        return $this->successResponse($media, 'Medya listesi hazir.', 200, [
            'filters' => ['type' => $validated['type'] ?? null, 'sort_by' => $sortBy, 'sort_dir' => $sortDir],
        ]);
    }

    public function publicIndexByUser(int $id): JsonResponse
    {
        $validated = request()->validate([
            'type'     => ['nullable', 'in:image,video'],
            'sort_by'  => ['nullable', 'in:created_at,title,type'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $player = User::query()
            ->whereKey($id)
            ->where('role', 'player')
            ->first();

        if (! $player) {
            return $this->errorResponse('Oyuncu bulunamadi.', Response::HTTP_NOT_FOUND, 'player_not_found');
        }

        $query = Media::query()->where('user_id', $id);
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $sortBy  = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $media = $query->orderBy($sortBy, $sortDir)->paginate((int) ($validated['per_page'] ?? 20));
        $media->getCollection()->transform(fn (Media $item) => $this->transformMedia($item));

        return $this->successResponse($media, 'Public medya listesi hazir.', 200, [
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

    private function resolveGuestUploader(): User
    {
        return User::firstOrCreate(
            ['email' => 'guest-scout@nextscout.local'],
            [
                'name' => 'Guest Scout Pool',
                'password' => Hash::make(Str::random(32)),
                'role' => 'scout',
                'is_verified' => true,
                'email_verified_at' => now(),
                'subscription_status' => 'free',
                'is_public' => false,
            ]
        );
    }

    private function transformMedia(Media $media): array
    {
        return [
            'id' => (int) $media->id,
            'type' => (string) $media->type,
            'url' => $this->publicFileUrl($media->url),
            'thumb_url' => $this->publicFileUrl($media->thumb_url),
            'title' => $media->title,
            'created_at' => optional($media->created_at)?->toIso8601String(),
            'updated_at' => optional($media->updated_at)?->toIso8601String(),
        ];
    }
}
