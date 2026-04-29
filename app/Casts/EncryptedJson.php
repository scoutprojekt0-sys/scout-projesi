<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EncryptedJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            return null;
        }

        $encrypted = $decoded['_encrypted'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return $decoded;
        }

        $plaintext = Crypt::decryptString($encrypted);
        $payload = json_decode($plaintext, true);

        return is_array($payload) ? $payload : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }

        return json_encode([
            '_encrypted' => Crypt::encryptString($json),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
