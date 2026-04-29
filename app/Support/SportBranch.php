<?php

namespace App\Support;

class SportBranch
{
    public static function allowedInputs(): array
    {
        return [
            'football',
            'basketball',
            'volleyball',
            'athletics',
            'tennis',
            'futbol',
            'basketbol',
            'voleybol',
            'atletizm',
            'tenis',
        ];
    }

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'football', 'futbol' => 'futbol',
            'basketball', 'basketbol' => 'basketbol',
            'volleyball', 'voleybol' => 'voleybol',
            'athletics', 'atletizm' => 'atletizm',
            'tennis', 'tenis' => 'tenis',
            default => null,
        };
    }

    public static function label(mixed $value): ?string
    {
        return match (self::normalize($value)) {
            'futbol' => 'Futbol',
            'basketbol' => 'Basketbol',
            'voleybol' => 'Voleybol',
            'atletizm' => 'Atletizm',
            'tenis' => 'Tenis',
            default => null,
        };
    }
}
