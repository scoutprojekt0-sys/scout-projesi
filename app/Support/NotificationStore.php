<?php

namespace App\Support;

use App\Services\FirebasePushService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class NotificationStore
{
    public static function sendToUser(
        int $userId,
        string $type,
        array $payload = [],
        ?string $title = null,
        ?string $message = null,
        string $priority = 'low',
        ?int $relatedPlayerId = null,
        ?string $pushPreferenceKey = null,
    ): void {
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'priority' => $priority,
            'related_player_id' => $relatedPlayerId,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget("notifications_count_{$userId}");

        self::dispatchPush([$userId], $type, $payload, $title, $message, $pushPreferenceKey);
    }

    public static function sendToUsers(
        iterable $userIds,
        string $type,
        array $payload = [],
        ?string $title = null,
        ?string $message = null,
        string $priority = 'low',
        ?int $relatedPlayerId = null,
        ?string $pushPreferenceKey = null,
    ): void {
        $rows = [];
        $now = now();
        $normalizedUserIds = [];

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }

            $normalizedUserIds[] = $userId;

            $rows[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'priority' => $priority,
                'related_player_id' => $relatedPlayerId,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            Cache::forget("notifications_count_{$userId}");
        }

        if ($rows !== []) {
            DB::table('notifications')->insert($rows);
        }

        self::dispatchPush($normalizedUserIds, $type, $payload, $title, $message, $pushPreferenceKey);
    }

    private static function dispatchPush(
        array $userIds,
        string $type,
        array $payload,
        ?string $title,
        ?string $message,
        ?string $pushPreferenceKey,
    ): void {
        try {
            app(FirebasePushService::class)->sendToUsers(
                $userIds,
                $title,
                $message,
                array_merge($payload, ['type' => $type]),
                $pushPreferenceKey ?? self::inferPushPreferenceKey($type)
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function inferPushPreferenceKey(string $type): ?string
    {
        return match (true) {
            str_contains($type, 'message') => 'allow_inbox_push',
            str_contains($type, 'offer'),
            str_contains($type, 'trial'),
            str_contains($type, 'watch'),
            str_contains($type, 'interest') => 'allow_offer_alerts',
            str_contains($type, 'match') => 'allow_match_alerts',
            default => null,
        };
    }
}
