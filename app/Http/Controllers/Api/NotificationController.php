<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                    'payload' => $payload,
                    'is_read' => (bool) $notification->is_read,
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
}
