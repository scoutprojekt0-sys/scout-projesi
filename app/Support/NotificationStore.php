<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
    }

    public static function sendToUsers(
        iterable $userIds,
        string $type,
        array $payload = [],
        ?string $title = null,
        ?string $message = null,
        string $priority = 'low',
        ?int $relatedPlayerId = null,
    ): void {
        $rows = [];
        $now = now();

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }

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
    }
}
