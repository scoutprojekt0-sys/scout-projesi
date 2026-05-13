<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:20'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $record = DevicePushToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'] ?? null,
                'notifications_enabled' => $validated['notifications_enabled'] ?? true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Push token kaydedildi.',
            'data' => [
                'id' => $record->id,
                'token' => $record->token,
                'platform' => $record->platform,
                'notifications_enabled' => (bool) $record->notifications_enabled,
                'last_seen_at' => optional($record->last_seen_at)?->toISOString(),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        DevicePushToken::query()
            ->where('user_id', $user->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Push token silindi.',
        ]);
    }
}
