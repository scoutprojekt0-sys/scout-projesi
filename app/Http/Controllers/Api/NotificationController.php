<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use App\Models\UserNotificationPreference;
use App\Support\NotificationStore;
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
                'allow_push_notifications' => true,
                'allow_inbox_push' => true,
                'allow_offer_alerts' => true,
                'sport' => $user->sport,
                'city' => $user->city,
                'district' => null,
            ]
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'allow_match_alerts' => (bool) $preferences->allow_match_alerts,
                'allow_push_notifications' => (bool) $preferences->allow_push_notifications,
                'allow_inbox_push' => (bool) $preferences->allow_inbox_push,
                'allow_offer_alerts' => (bool) $preferences->allow_offer_alerts,
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
            'allow_push_notifications' => ['nullable', 'boolean'],
            'allow_inbox_push' => ['nullable', 'boolean'],
            'allow_offer_alerts' => ['nullable', 'boolean'],
            'sport' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['allow_push_notifications'] = array_key_exists('allow_push_notifications', $validated)
            ? (bool) $validated['allow_push_notifications']
            : true;
        $validated['allow_inbox_push'] = array_key_exists('allow_inbox_push', $validated)
            ? (bool) $validated['allow_inbox_push']
            : true;
        $validated['allow_offer_alerts'] = array_key_exists('allow_offer_alerts', $validated)
            ? (bool) $validated['allow_offer_alerts']
            : true;

        $preferences = UserNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        if (! $preferences->allow_push_notifications) {
            DevicePushToken::query()
                ->where('user_id', $user->id)
                ->update(['notifications_enabled' => false, 'updated_at' => now()]);
        } else {
            DevicePushToken::query()
                ->where('user_id', $user->id)
                ->update(['notifications_enabled' => true, 'updated_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Bildirim tercihleri guncellendi.',
            'data' => [
                'allow_match_alerts' => (bool) $preferences->allow_match_alerts,
                'allow_push_notifications' => (bool) $preferences->allow_push_notifications,
                'allow_inbox_push' => (bool) $preferences->allow_inbox_push,
                'allow_offer_alerts' => (bool) $preferences->allow_offer_alerts,
                'sport' => $preferences->sport,
                'city' => $preferences->city,
                'district' => $preferences->district,
            ],
        ]);
    }

    public function sendPlayerSignal(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'player_user_id' => ['required', 'integer', 'exists:users,id'],
            'signal_type' => ['required', 'string', 'in:interest,watch'],
        ]);

        $playerUserId = (int) $validated['player_user_id'];
        $signalType = (string) $validated['signal_type'];
        $actorName = trim((string) ($user->name ?? ''));
        $actorRole = trim((string) ($user->role ?? ''));
        $roleLabel = match ($actorRole) {
            'manager' => 'Menajer',
            'coach' => 'Antrenor',
            'team', 'club' => 'Kulup',
            'scout' => 'Scout',
            default => 'Kullanici',
        };

        $title = $signalType === 'interest' ? 'Ilgi Bildirimi' : 'Izleme Bildirimi';
        $message = $signalType === 'interest'
            ? sprintf('%s (%s) profilinize ilgi gosterdi.', $actorName !== '' ? $actorName : 'Bir kullanici', $roleLabel)
            : sprintf('%s (%s) profilinizi izleme listesine ekledi.', $actorName !== '' ? $actorName : 'Bir kullanici', $roleLabel);

        NotificationStore::sendToUser(
            $playerUserId,
            $signalType === 'interest' ? 'player_interest_signal' : 'player_watch_signal',
            [
                'actor_user_id' => (int) $user->id,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'player_id' => $playerUserId,
                'type' => $signalType,
            ],
            $title,
            $message,
            'medium',
            $playerUserId,
        );

        return response()->json([
            'ok' => true,
            'message' => 'Oyuncu bildirimi gonderildi.',
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
