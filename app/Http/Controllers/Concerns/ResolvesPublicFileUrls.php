<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;

trait ResolvesPublicFileUrls
{
    private function publicFileUrl(?string $value): ?string
    {
        $path = $this->extractPublicDiskPath($value);

        if ($path === null) {
            return $value;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $this->publicFileAssetUrl($path);
    }

    private function publicFileAssetUrl(string $path): string
    {
        $normalizedPath = implode('/', array_map('rawurlencode', array_filter(explode('/', trim($path, '/')), static fn ($segment) => $segment !== '')));
        $baseUrl = rtrim((string) config('app.url', url('/')), '/');

        return $baseUrl.'/storage/'.$normalizedPath;
    }

    private function extractPublicDiskPath(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (! str_contains($raw, '://') && ! str_starts_with($raw, '/')) {
            return ltrim($raw, '/');
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, '/media-files/')) {
            return ltrim(substr($path, strlen('/media-files/')), '/');
        }

        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        return null;
    }
}
