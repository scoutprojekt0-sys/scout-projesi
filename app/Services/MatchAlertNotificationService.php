<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\PlayerMatchSchedule;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MatchAlertNotificationService
{
    public function dispatchForSchedule(PlayerMatchSchedule $schedule): void
    {
        if (! $schedule->is_public) {
            return;
        }

        $schedule->loadMissing('player:id,name,role,sport,city,position');

        $player = $schedule->player;
        if (! $player) {
            return;
        }

        $sport = $this->normalizeValue($player->sport);
        if ($sport === '') {
            return;
        }

        $city = $this->normalizeValue($schedule->city ?: $player->city);
        $district = $this->normalizeValue($schedule->district);
        $position = $this->normalizeValue($schedule->position ?: $player->position);
        $favoriteUserIds = Favorite::query()
            ->where('target_user_id', $player->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $recipients = $this->findRecipients($player->id, $sport, $city);
        if ($recipients->isEmpty()) {
            return;
        }

        $existingRecipientIds = DB::table('notifications')
            ->where('type', 'player_match_alert')
            ->where('related_match_schedule_id', $schedule->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $title = trim(sprintf('%s mac bildirimi', $player->name ?: 'Oyuncu'));
        $message = trim(sprintf(
            '%s, %s tarihinde %s bolgesinde izlenebilir bir mac paylasti.',
            $player->name ?: 'Oyuncu',
            optional($schedule->match_date)->format('Y-m-d H:i') ?: 'yakinda',
            $schedule->district ?: ($schedule->city ?: 'belirtilen')
        ));

        $rows = [];
        $now = now();

        foreach ($recipients as $recipient) {
            if (in_array((int) $recipient->id, $existingRecipientIds, true)) {
                continue;
            }

            $priority = $this->resolvePriority(
                recipientCity: $this->normalizeValue($recipient->preference_city ?: $recipient->city),
                recipientDistrict: $this->normalizeValue($recipient->preference_district),
                scheduleCity: $city,
                scheduleDistrict: $district,
                positionMatched: $position !== '' && $this->normalizeValue($recipient->position) === $position,
                isFavorite: in_array((int) $recipient->id, $favoriteUserIds, true),
            );

            $rows[] = [
                'user_id' => (int) $recipient->id,
                'type' => 'player_match_alert',
                'title' => $title,
                'message' => $message,
                'payload' => json_encode([
                    'player_id' => (int) $player->id,
                    'player_name' => $player->name,
                    'sport' => $player->sport,
                    'position' => $schedule->position ?: $player->position,
                    'match_title' => $schedule->match_title,
                    'match_date' => optional($schedule->match_date)->toIso8601String(),
                    'city' => $schedule->city,
                    'district' => $schedule->district,
                    'venue' => $schedule->venue,
                    'deep_link' => '/player-match-schedules/' . $schedule->id,
                ], JSON_UNESCAPED_UNICODE),
                'priority' => $priority,
                'is_read' => false,
                'read_at' => null,
                'related_player_id' => (int) $player->id,
                'related_match_schedule_id' => (int) $schedule->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            Cache::forget("notifications_count_{$recipient->id}");
        }

        if ($rows !== []) {
            DB::table('notifications')->insert($rows);
        }
    }

    private function findRecipients(int $playerId, string $sport, string $city): Collection
    {
        return User::query()
            ->leftJoin('user_notification_preferences as unp', 'unp.user_id', '=', 'users.id')
            ->whereIn('users.role', ['coach', 'manager', 'scout', 'team'])
            ->where('users.id', '!=', $playerId)
            ->where(function ($query) use ($sport) {
                $query->whereRaw('LOWER(COALESCE(unp.sport, users.sport, "")) = ?', [$sport]);
            })
            ->where(function ($query) use ($city) {
                $query
                    ->whereNull('unp.city')
                    ->orWhereRaw('LOWER(unp.city) = ?', [$city])
                    ->orWhereRaw('LOWER(COALESCE(users.city, "")) = ?', [$city]);
            })
            ->where(function ($query) {
                $query->whereNull('unp.allow_match_alerts')
                    ->orWhere('unp.allow_match_alerts', true);
            })
            ->select([
                'users.id',
                'users.city',
                'users.position',
                'unp.city as preference_city',
                'unp.district as preference_district',
            ])
            ->get();
    }

    private function resolvePriority(
        string $recipientCity,
        string $recipientDistrict,
        string $scheduleCity,
        string $scheduleDistrict,
        bool $positionMatched,
        bool $isFavorite
    ): string {
        $sameCity = $recipientCity !== '' && $recipientCity === $scheduleCity;
        $sameDistrict = $recipientDistrict !== '' && $scheduleDistrict !== '' && $recipientDistrict === $scheduleDistrict;

        if ($isFavorite && $sameCity) {
            return 'high';
        }

        if ($sameCity && ($sameDistrict || $positionMatched)) {
            return 'medium';
        }

        return 'low';
    }

    private function normalizeValue(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
