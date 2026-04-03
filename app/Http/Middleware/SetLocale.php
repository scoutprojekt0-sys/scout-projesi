<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Query parametreden locale al (önce)
        $locale = $request->query('locale');

        // Eğer query'de yoksa, header'dan al
        if (! $locale) {
            $acceptLanguage = $request->header('Accept-Language');
            if ($acceptLanguage) {
                // Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7
                $locales = explode(',', $acceptLanguage);
                $locale = strtolower(substr($locales[0], 0, 2)); // Sadece dil kodu al (tr)
            }
        }

        // Eğer hâlâ yoksa, varsayılanı kullan
        if (! $locale) {
            $locale = config('localization.default', 'tr');
        }

        // Desteklenen locales kontrol et
        $supported = config('localization.supported_locales', ['tr', 'en']);
        if (! in_array($locale, $supported)) {
            $locale = config('localization.default', 'tr');
        }

        // Locale'i ayarla
        app()->setLocale($locale);

        // BinaryFileResponse gibi response tiplerinde header() zinciri yok;
        // ortak headers koleksiyonu uzerinden set etmek guvenli.
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
