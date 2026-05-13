<?php

namespace App\Services;

use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function isConfigured(): bool
    {
        $projectId = (string) config('services.firebase_messaging.project_id', '');
        $serviceAccount = $this->serviceAccount();

        return $projectId !== '' && $serviceAccount !== null;
    }

    public function sendToUsers(
        iterable $userIds,
        ?string $title,
        ?string $body,
        array $data = [],
        ?string $preferenceKey = null,
    ): void {
        if (! $this->isConfigured()) {
            return;
        }

        $normalizedIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return;
        }

        $query = DevicePushToken::query()
            ->whereIn('user_id', $normalizedIds)
            ->where('notifications_enabled', true)
            ->whereNotNull('token');

        $query->where(function ($builder) use ($preferenceKey) {
            $builder->whereDoesntHave('user.notificationPreference')
                ->orWhereHas('user.notificationPreference', function ($preferenceBuilder) use ($preferenceKey) {
                    $preferenceBuilder->where('allow_push_notifications', true);

                    if ($preferenceKey !== null) {
                        $preferenceBuilder->where($preferenceKey, true);
                    }
                });
        });

        $tokens = $query->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return;
        }

        foreach ($tokens as $tokenRow) {
            $this->sendMessage(
                token: (string) $tokenRow->token,
                title: $title,
                body: $body,
                data: $data,
                accessToken: $accessToken,
            );
        }
    }

    private function sendMessage(
        string $token,
        ?string $title,
        ?string $body,
        array $data,
        string $accessToken,
    ): void {
        $projectId = (string) config('services.firebase_messaging.project_id');

        $response = Http::withToken($accessToken)
            ->timeout((int) config('services.firebase_messaging.timeout_seconds', 10))
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => array_filter([
                        'title' => $title,
                        'body' => $body,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'data' => $this->normalizeData($data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return;
        }

        $payload = $response->json();
        $errorCode = data_get($payload, 'error.details.0.errorCode')
            ?? data_get($payload, 'error.status');

        if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
            DevicePushToken::query()->where('token', $token)->delete();
            return;
        }

        Log::warning('firebase push send failed', [
            'status' => $response->status(),
            'token' => $token,
            'error' => $payload,
        ]);
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $normalized[(string) $key] = $value === null ? '' : (string) $value;
        }

        return $normalized;
    }

    private function accessToken(): ?string
    {
        $cacheKey = 'firebase_push_access_token';

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $serviceAccount = $this->serviceAccount();
            if ($serviceAccount === null) {
                return null;
            }

            $jwt = $this->buildJwt($serviceAccount);
            $tokenUri = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            $response = Http::asForm()
                ->timeout((int) config('services.firebase_messaging.timeout_seconds', 10))
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $response->successful()) {
                Log::warning('firebase push token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function buildJwt(array $serviceAccount): string
    {
        $now = now()->timestamp;
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_UNESCAPED_SLASHES));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'sub' => $serviceAccount['client_email'],
            'aud' => $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ], JSON_UNESCAPED_SLASHES));

        $signingInput = $header.'.'.$claims;
        $privateKey = openssl_pkey_get_private(str_replace('\n', "\n", (string) $serviceAccount['private_key']));
        $signature = '';
        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function serviceAccount(): ?array
    {
        $inlineJson = (string) config('services.firebase_messaging.credentials_json', '');
        if ($inlineJson !== '') {
            $decoded = json_decode($inlineJson, true);
            if (is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'])) {
                return $decoded;
            }
        }

        $path = (string) config('services.firebase_messaging.credentials_path', '');
        if ($path !== '' && is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'])) {
                return $decoded;
            }
        }

        return null;
    }
}
