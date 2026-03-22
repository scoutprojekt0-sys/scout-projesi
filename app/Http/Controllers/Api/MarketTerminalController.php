<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MarketTerminalController extends Controller
{
    use ApiResponds;

    public function liveFeed(): JsonResponse
    {
        $videoRows = DB::table('video_clips as vc')
            ->join('users as u', 'u.id', '=', 'vc.user_id')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'u.id')
            ->leftJoin('favorites as f', 'f.target_user_id', '=', 'u.id')
            ->selectRaw('
                vc.id,
                vc.title,
                vc.created_at,
                COALESCE(NULLIF(pp.position, \'\'), NULLIF(u.position, \'\'), \'Oyuncu\') as position_name,
                u.id as player_id,
                u.name as player_name,
                COALESCE(vc.view_count, 0) as view_count,
                COUNT(f.id) as favorites_count
            ')
            ->groupBy([
                'vc.id',
                'vc.title',
                'vc.created_at',
                'pp.position',
                'u.position',
                'u.id',
                'u.name',
                'vc.view_count',
            ])
            ->orderByDesc('vc.created_at')
            ->limit(8)
            ->get();

        $favoriteRows = DB::table('favorites as f')
            ->join('users as u', 'u.id', '=', 'f.target_user_id')
            ->leftJoin('player_profiles as pp', 'pp.user_id', '=', 'u.id')
            ->where('u.role', 'player')
            ->where('f.created_at', '>=', now()->subDay())
            ->selectRaw('
                u.id as player_id,
                u.name as player_name,
                COALESCE(NULLIF(pp.position, \'\'), NULLIF(u.position, \'\'), \'Oyuncu\') as position_name,
                COUNT(f.id) as favorite_count,
                MAX(f.created_at) as latest_favorite_at
            ')
            ->groupBy([
                'u.id',
                'u.name',
                'pp.position',
                'u.position',
            ])
            ->orderByDesc('favorite_count')
            ->orderByDesc('latest_favorite_at')
            ->limit(8)
            ->get();

        $trialRows = DB::table('opportunities as o')
            ->join('users as u', 'u.id', '=', 'o.team_user_id')
            ->where('o.status', 'open')
            ->where('o.created_at', '>=', now()->startOfDay())
            ->selectRaw('
                COALESCE(NULLIF(o.position, \'\'), \'Oyuncu\') as position_name,
                COUNT(o.id) as total_count,
                MAX(o.created_at) as latest_created_at,
                SUM(CASE WHEN u.role = \'coach\' THEN 1 ELSE 0 END) as coach_sessions,
                SUM(CASE WHEN u.role IN (\'team\', \'club\', \'manager\') THEN 1 ELSE 0 END) as trial_invites
            ')
            ->groupBy('o.position')
            ->orderByDesc('total_count')
            ->limit(6)
            ->get();

        $scheduleRows = DB::table('player_match_schedules as pms')
            ->join('users as u', 'u.id', '=', 'pms.player_user_id')
            ->where('pms.is_public', true)
            ->whereBetween('pms.match_date', [now()->startOfDay(), now()->copy()->addDays(1)->endOfDay()])
            ->selectRaw('
                pms.id,
                pms.match_title,
                pms.city,
                pms.venue,
                pms.match_date,
                COALESCE(NULLIF(pms.position, \'\'), NULLIF(u.position, \'\'), \'Oyuncu\') as position_name,
                u.id as player_id,
                u.name as player_name
            ')
            ->orderBy('pms.match_date')
            ->limit(8)
            ->get();

        $videoItems = $videoRows->map(function ($row) {
            $interestIndex = min(
                99,
                max(
                    12,
                    18 + ((int) ($row->view_count ?? 0) * 2) + ((int) ($row->favorites_count ?? 0) * 4)
                )
            );
            $delta = min(34, max(4, (int) round($interestIndex * 0.28)));

            return [
                'kind' => 'video_upload',
                'tone' => 'up',
                'badge' => 'VIDEO',
                'symbol' => 'V',
                'player_id' => (int) $row->player_id,
                'player_name' => (string) ($row->player_name ?? 'Oyuncu'),
                'position' => $this->normalizePosition($row->position_name),
                'headline' => 'Antrenman videosu yuklendi',
                'detail' => 'Ilgi endeksi +' . $delta . '%',
                'metric_label' => 'Ilgi Endeksi',
                'metric_value' => $interestIndex,
                'change_percent' => $delta,
                'heat_label' => $this->heatLabelFromScore($interestIndex),
                'event_time' => $this->iso($row->created_at),
                'market_time' => $this->clock($row->created_at),
            ];
        });

        $favoriteItems = $favoriteRows->map(function ($row) {
            $count = (int) ($row->favorite_count ?? 0);
            $heatScore = min(95, 25 + ($count * 18));

            return [
                'kind' => 'favorite_spike',
                'tone' => $count >= 4 ? 'hot' : 'watch',
                'badge' => 'FAV',
                'symbol' => 'F',
                'player_id' => (int) $row->player_id,
                'player_name' => (string) ($row->player_name ?? 'Oyuncu'),
                'position' => $this->normalizePosition($row->position_name),
                'headline' => $count . ' kulup favoriye ekledi',
                'detail' => 'Sicak bolge: ' . $this->heatLabelFromScore($heatScore),
                'metric_label' => 'Favori Akisi',
                'metric_value' => $count,
                'change_percent' => min(48, 8 + ($count * 6)),
                'heat_label' => $this->heatLabelFromScore($heatScore),
                'event_time' => $this->iso($row->latest_favorite_at),
                'market_time' => $this->clock($row->latest_favorite_at),
            ];
        });

        $trialItems = $trialRows->map(function ($row) {
            $count = (int) ($row->trial_invites ?: $row->total_count);
            $marketPressure = min(98, 22 + ($count * 7));

            return [
                'kind' => 'trial_stat',
                'tone' => 'info',
                'badge' => 'STAT',
                'symbol' => 'S',
                'player_id' => null,
                'player_name' => mb_convert_case($this->normalizePosition($row->position_name), MB_CASE_TITLE, 'UTF-8'),
                'position' => $this->normalizePosition($row->position_name),
                'headline' => 'Bugun ' . $count . ' ' . mb_strtolower($this->normalizePosition($row->position_name), 'UTF-8') . ' trial daveti aldi',
                'detail' => 'Pazar baskisi: ' . $this->heatLabelFromScore($marketPressure),
                'metric_label' => 'Trial Daveti',
                'metric_value' => $count,
                'change_percent' => min(40, 6 + ($count * 2)),
                'heat_label' => $this->heatLabelFromScore($marketPressure),
                'event_time' => $this->iso($row->latest_created_at),
                'market_time' => $this->clock($row->latest_created_at),
            ];
        });

        $scheduleItems = $scheduleRows->map(function ($row) {
            return [
                'kind' => 'live_window',
                'tone' => 'neutral',
                'badge' => 'LIVE',
                'symbol' => 'L',
                'player_id' => (int) $row->player_id,
                'player_name' => (string) ($row->player_name ?? 'Oyuncu'),
                'position' => $this->normalizePosition($row->position_name),
                'headline' => ($row->city ?: 'Sehir') . ' icin acik mac penceresi',
                'detail' => ($row->venue ?: 'Saha bilgisi') . ' -> ' . ($row->match_title ?: 'Canli takip kaydi'),
                'metric_label' => 'Canli Pencere',
                'metric_value' => 1,
                'change_percent' => 0,
                'heat_label' => 'Izleniyor',
                'event_time' => $this->iso($row->match_date),
                'market_time' => $this->clock($row->match_date),
            ];
        });

        $ticker = $videoItems
            ->concat($favoriteItems)
            ->concat($trialItems)
            ->concat($scheduleItems)
            ->sortByDesc('event_time')
            ->take(18)
            ->values();

        $sectorMap = $trialRows
            ->map(function ($row) {
                $count = (int) ($row->trial_invites ?: $row->total_count);
                return [
                    'position' => $this->normalizePosition($row->position_name),
                    'count' => $count,
                    'heat_label' => $this->heatLabelFromScore(22 + ($count * 7)),
                ];
            })
            ->values();

        $summary = [
            'open_opportunities' => (int) DB::table('opportunities')->where('status', 'open')->count(),
            'videos_today' => (int) DB::table('video_clips')->where('created_at', '>=', now()->startOfDay())->count(),
            'favorites_today' => (int) DB::table('favorites')->where('created_at', '>=', now()->startOfDay())->count(),
            'trial_invites_today' => (int) $trialRows->sum(fn ($row) => (int) ($row->trial_invites ?: $row->total_count)),
            'public_match_windows' => (int) DB::table('player_match_schedules')
                ->where('is_public', true)
                ->whereBetween('match_date', [now()->startOfDay(), now()->copy()->addDays(1)->endOfDay()])
                ->count(),
        ];

        return $this->successResponse([
            'ticker' => $ticker,
            'top_videos' => $videoItems->take(5)->values(),
            'favorite_movers' => $favoriteItems->take(5)->values(),
            'trial_pressure' => $trialItems->take(5)->values(),
            'live_windows' => $scheduleItems->take(5)->values(),
            'sectors' => $sectorMap,
            'summary' => $summary,
            'generated_at' => now()->toIso8601String(),
        ], 'Borsa terminali akisi hazir.');
    }

    private function normalizePosition(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 'Oyuncu';
        }

        $compact = mb_strtolower($raw, 'UTF-8');

        return match (true) {
            str_contains($compact, 'sol bek'), str_contains($compact, 'left back'), $compact === 'lb' => 'Sol Bek',
            str_contains($compact, 'sag bek'), str_contains($compact, 'sağ bek'), str_contains($compact, 'right back'), $compact === 'rb' => 'Sag Bek',
            str_contains($compact, 'stoper'), str_contains($compact, 'center back'), $compact === 'cb' => 'Stoper',
            str_contains($compact, 'forvet'), str_contains($compact, 'santrafor'), str_contains($compact, 'striker') => 'Forvet',
            str_contains($compact, 'orta saha'), str_contains($compact, 'midfield'), str_contains($compact, '10 numara'), str_contains($compact, '8 numara') => 'Orta Saha',
            str_contains($compact, 'kanat'), str_contains($compact, 'wing') => 'Kanat',
            str_contains($compact, 'kaleci'), str_contains($compact, 'goalkeeper') => 'Kaleci',
            default => $raw,
        };
    }

    private function heatLabelFromScore(int $score): string
    {
        return match (true) {
            $score >= 82 => 'Yuksek',
            $score >= 56 => 'Orta',
            default => 'Dusuk',
        };
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return date(DATE_ATOM, strtotime((string) $value));
    }

    private function clock(mixed $value): string
    {
        if ($value === null) {
            return 'Simdi';
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return 'Simdi';
        }

        return date('H:i', $timestamp);
    }
}
