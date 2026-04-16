<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\EnforcesPrivacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemController extends Controller
{
    use ApiResponds;
    use EnforcesPrivacy;

    public function ping(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'service' => 'Scout API',
        ], 'Ping basarili.');
    }

    public function notificationsCount(): JsonResponse
    {
        $user = auth()->user();

        $count = Cache::remember(
            "notifications_count_{$user->id}",
            300,
            fn() => DB::table('notifications')
                ->where('user_id', $user->id)
                ->where('is_read', false)
                ->count()
        );

        return $this->successResponse(['count' => $count], 'Bildirim sayisi hazir.');
    }

    public function adminRateLimitSummary(): JsonResponse
    {
        $dayKey = now()->format('Ymd');
        $rateLimitPerMinute = (int) env('RATE_LIMIT_API', 60);

        return $this->successResponse([
            'day' => $dayKey,
            'rate_limit_per_minute' => $rateLimitPerMinute,
            'request_window_minutes' => 1,
            'requests_total_today' => (int) Cache::get("ops:requests:{$dayKey}:total", 0),
            'rate_limited_today' => (int) Cache::get("ops:requests:{$dayKey}:rate_limited", 0),
            'server_errors_today' => (int) Cache::get("ops:requests:{$dayKey}:server_error", 0),
            'requests_2xx_today' => (int) Cache::get("ops:requests:{$dayKey}:status:200", 0),
        ], 'Rate limit ozeti hazir.');
    }

    public function usersIndex(Request $request): JsonResponse
    {
        $role = strtolower(trim((string) $request->query('role', '')));
        $query = trim((string) $request->query('q', ''));
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        if ($role === 'club') {
            $role = 'team';
        }

        $users = DB::table('users')
            ->when($role !== '', fn ($q) => $q->where('role', $role))
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner
                        ->where('name', 'like', '%'.$query.'%')
                        ->orWhere('email', 'like', '%'.$query.'%');
                });
            })
            ->select('id', 'name', 'email', 'role', 'created_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        // Admin değilse email maskele
        if (! $this->isAdmin(request()->user())) {
            $users->getCollection()->transform(function ($u) {
                $u->email = $this->maskEmail((string) $u->email);
                return $u;
            });
        }

        return $this->paginatedListResponse($users, 'Kullanici listesi hazir.');
    }

    public function destroyUser(int $id): JsonResponse
    {
        $user = DB::table('users')
            ->where('id', $id)
            ->select('id', 'email', 'role')
            ->first();

        if (! $user) {
            return $this->errorResponse('Kullanici bulunamadi.', 404, 'not_found');
        }

        DB::transaction(function () use ($id): void {
            $this->deleteUserRelations([$id]);

            DB::table('users')->where('id', $id)->delete();
        });

        return $this->successResponse([
            'id' => $id,
            'email' => $user->email,
            'role' => $user->role,
        ], 'Kullanici silindi.');
    }

    public function userProfileCard(Request $request, int $id): JsonResponse
    {
        $user = DB::table('users')
            ->where('id', $id)
            ->select('id', 'name', 'email', 'role', 'city', 'phone', 'created_at', 'updated_at')
            ->first();

        if (! $user) {
            return $this->errorResponse('Kullanici bulunamadi.', 404, 'not_found');
        }

        $authUser = $request->user();
        $isOwner = $authUser && (int) $authUser->id === (int) $id;
        $isAdmin = $this->isAdmin($authUser);
        $canSeePrivate = $isOwner || $isAdmin;

        $role = strtolower((string) $user->role);
        $profile = null;

        if ($role === 'player' && Schema::hasTable('player_profiles')) {
            $profile = DB::table('player_profiles')
                ->where('user_id', $id)
                ->select('birth_year', 'position', 'dominant_foot', 'height_cm', 'weight_kg', 'current_team', 'bio', 'updated_at')
                ->first();
        } elseif ($role === 'team' && Schema::hasTable('team_profiles')) {
            $profile = DB::table('team_profiles')
                ->where('user_id', $id)
                ->select('team_name', 'league_level', 'city', 'founded_year', 'needs_text', 'updated_at')
                ->first();
        } elseif (in_array($role, ['manager', 'coach', 'scout'], true) && Schema::hasTable('staff_profiles')) {
            $profile = DB::table('staff_profiles')
                ->where('user_id', $id)
                ->select('role_type', 'organization', 'experience_years', 'bio', 'updated_at')
                ->first();
        }

        $socialAccounts = [];
        if ($canSeePrivate && Schema::hasTable('social_media_accounts')) {
            $socialAccounts = DB::table('social_media_accounts')
                ->where('user_id', $id)
                ->select('platform', 'username', 'url', 'follower_count', 'verified')
                ->orderBy('platform')
                ->get();
        }

        $mediaCount = 0;
        if (Schema::hasTable('media')) {
            $mediaCount = DB::table('media')->where('user_id', $id)->count();
        }

        $latestUserAgent = null;
        $lastActiveAt = null;
        if (Schema::hasTable('personal_access_tokens') && Schema::hasColumn('personal_access_tokens', 'user_agent')) {
            $latestUserAgent = DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->where('tokenable_id', $id)
                ->orderByDesc('id')
                ->value('user_agent');

            if (Schema::hasColumn('personal_access_tokens', 'last_used_at')) {
                $lastActiveAt = DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->where('tokenable_id', $id)
                    ->whereNotNull('last_used_at')
                    ->orderByDesc('last_used_at')
                    ->value('last_used_at');
            }
        }

        $lastLoginAt = null;
        if (Schema::hasColumn('users', 'last_login_at')) {
            $lastLoginAt = DB::table('users')->where('id', $id)->value('last_login_at');
        }

        $registrationSource = 'unknown';
        $ua = strtolower((string) $latestUserAgent);
        if ($ua !== '') {
            if (str_contains($ua, 'flutter') || str_contains($ua, 'dart') || str_contains($ua, 'android') || str_contains($ua, 'iphone') || str_contains($ua, 'ios')) {
                $registrationSource = 'mobile';
            } else {
                $registrationSource = 'web';
            }
        }

        if (! $canSeePrivate) {
            $user = $this->redactPrivateFields($user, false);
        }

        return $this->successResponse([
                'user' => $user,
                'profile' => $profile,
                'social_accounts' => $socialAccounts,
                'media_count' => $mediaCount,
                'registration_source' => $registrationSource,
                'last_login_at' => $lastLoginAt,
                'last_active_at' => $lastActiveAt,
            ], 'Profil karti hazir.');
    }

    private function deleteUserRelations(array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return;
        }

        $tableColumnMap = [
            'player_profiles' => ['user_id'],
            'team_profiles' => ['user_id'],
            'staff_profiles' => ['user_id'],
            'social_media_accounts' => ['user_id'],
            'media' => ['user_id'],
            'notifications' => ['user_id'],
            'favorites' => ['user_id', 'target_user_id'],
            'profile_reviews' => ['reviewer_id', 'reviewed_user_id'],
            'profile_review_replies' => ['author_id'],
            'profile_review_reports' => ['reporter_id'],
            'profile_views' => ['viewer_user_id', 'viewed_user_id'],
            'contacts' => ['sender_user_id', 'receiver_user_id'],
            'live_matches' => ['created_by'],
            'live_watch_requests' => ['requester_user_id', 'target_user_id'],
            'applications' => ['applicant_user_id', 'reviewer_user_id'],
            'opportunities' => ['user_id'],
            'reports' => ['reporter_id', 'reported_user_id'],
            'scout_player_reports' => ['scout_user_id', 'player_user_id'],
            'scout_tip_watchlists' => ['user_id', 'target_user_id'],
            'scout_tip_role_requests' => ['requester_user_id', 'target_user_id'],
            'scout_tips' => ['user_id', 'target_user_id'],
            'support_tickets' => ['user_id'],
            'support_ticket_messages' => ['user_id'],
            'user_notification_preferences' => ['user_id'],
            'profile_views' => ['viewer_user_id', 'viewed_user_id'],
            'video_clips' => ['user_id'],
            'video_analyses' => ['user_id'],
            'video_analysis_targets' => ['user_id'],
            'video_analysis_clips' => ['user_id'],
            'video_analysis_events' => ['user_id'],
            'payments' => ['user_id'],
            'subscriptions' => ['user_id'],
            'player_boosts' => ['user_id'],
            'profile_review_replies' => ['author_id'],
            'profile_review_reports' => ['reporter_id'],
        ];

        foreach ($tableColumnMap as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn($table, $column)
            ));

            if ($existingColumns === []) {
                continue;
            }

            DB::table($table)->where(function ($query) use ($existingColumns, $userIds): void {
                foreach ($existingColumns as $index => $column) {
                    if ($index === 0) {
                        $query->whereIn($column, $userIds);
                    } else {
                        $query->orWhereIn($column, $userIds);
                    }
                }
            })->delete();
        }

        if (Schema::hasTable('personal_access_tokens')) {
            $query = DB::table('personal_access_tokens');

            if (Schema::hasColumn('personal_access_tokens', 'tokenable_type')) {
                $query->where('tokenable_type', 'App\\Models\\User');
            }

            if (Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
                $query->whereIn('tokenable_id', $userIds)->delete();
            }
        }
    }
}
