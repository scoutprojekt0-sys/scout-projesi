<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalizationController extends Controller
{
    use ApiResponds;

    public function getSupportedLocales(): JsonResponse
    {
        $locales = config('localization.supported_locales', ['tr', 'en']);
        $names   = config('localization.names', []);

        $data = collect($locales)->map(fn ($locale) => [
            'code' => $locale,
            'name' => $names[$locale] ?? $locale,
        ])->values();

        return $this->successResponse($data, 'Desteklenen diller hazir.', 200, [
            'current' => app()->getLocale(),
            'default' => config('localization.default', 'tr'),
        ]);
    }

    public function getTranslations(Request $request): JsonResponse
    {
        $locale   = $request->query('locale') ?? app()->getLocale();
        $messages = trans('messages', [], $locale);

        return $this->successResponse($messages, 'Ceviri anahtarlari hazir.', 200, [
            'locale' => $locale,
        ]);
    }
}
