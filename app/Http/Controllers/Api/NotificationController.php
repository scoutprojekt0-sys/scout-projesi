<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotificationPreference;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = max(1, min((int) $request->integer('limit', 20), 100));
        $onlyUnread = $request->boolean('unread');

        $notifications = DB::table('notifications')
            ->where('user_id', $user->id)
            ->when($onlyUnread, fn ($query) => $query->where('is_read', false))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($notification) {
                $payload = $notification->payload;
                if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    $payload = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                }

                return [
                    'id' => (int) $notification->id,
                    'type' => (string) $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'payload' => $this->sanitizePayload(is_array($payload) ? $payload : null),
                    'priority' => $notification->priority,
                    'is_read' => (bool) $notification->is_read,
                    'read_at' => $this->normalizeTimestamp($notification->read_at),
                    'related_player_id' => $notification->related_player_id ? (int) $notification->related_player_id : null,
                    'related_match_schedule_id' => $notification->related_match_schedule_id ? (int) $notification->related_match_schedule_id : null,
                    'created_at' => $this->normalizeTimestamp($notification->created_at),
                    'updated_at' => $this->normalizeTimestamp($notification->updated_at),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $notifications,
            'meta' => [
                'unread_count' => $this->unreadCount($user->id),
                'limit' => $limit,
                'filters' => [
                    'unread' => $onlyUnread,
                ],
            ],
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $updated = DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Bildirim bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->forgetUnreadCount($user->id);

        return response()->json([
            'ok' => true,
            'message' => 'Bildirim okundu olarak isaretlendi.',
            'data' => [
                'id' => $id,
                'unread_count' => $this->unreadCount($user->id),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = DB::table('notifications')
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        $this->forgetUnreadCount($user->id);

        return response()->json([
            'ok' => true,
            'message' => 'Tum bildirimler okundu olarak isaretlendi.',
            'data' => [
                'updated_count' => $updated,
                'unread_count' => $this->unreadCount($user->id),
            ],
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = UserNotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'allow_match_alerts' => true,
                'sport' => $user->sport,
                'city' => $user->city,
                'district' => null,
            ]
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'allow_match_alerts' => (bool) $preferences->allow_match_alerts,
                'sport' => $preferences->sport,
                'city' => $preferences->city,
                'district' => $preferences->district,
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'allow_match_alerts' => ['required', 'boolean'],
            'sport' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
        ]);

        $preferences = UserNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'ok' => true,
            'message' => 'Bildirim tercihleri guncellendi.',
            'data' => [
                'allow_match_alerts' => (bool) $preferences->allow_match_alerts,
                'sport' => $preferences->sport,
                'city' => $preferences->city,
                'district' => $preferences->district,
            ],
        ]);
    }

    private function unreadCount(int $userId): int
    {
        return Cache::remember(
            "notifications_count_{$userId}",
            300,
            fn () => DB::table('notifications')
                ->where('user_id', $userId)
                ->where('is_read', false)
                ->count()
        );
    }

    private function forgetUnreadCount(int $userId): void
    {
        Cache::forget("notifications_count_{$userId}");
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $allowedKeys = [
            'actor_user_id',
            'actor_name',
            'actor_role',
            'match_id',
            'match_title',
            'player_id',
            'player_name',
            'opportunity_id',
            'report_id',
            'message_id',
            'conversation_id',
            'url',
            'route',
            'action',
            'status',
            'type',
        ];

        $sanitized = [];
        foreach ($payload as $key => $value) {
            if (! in_array((string) $key, $allowedKeys, true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
