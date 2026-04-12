<?php

namespace App\Support;

use App\Models\ProfileReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProfileReviewData
{
    public static function paginateForTarget(int $targetUserId, ?User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        return self::baseQuery($targetUserId, $viewer)
            ->paginate($perPage)
            ->through(fn (ProfileReview $review) => self::formatReview($review));
    }

    public static function latestForTarget(int $targetUserId, ?User $viewer, int $limit = 10): array
    {
        $query = self::baseQuery($targetUserId, $viewer);
        $total = (clone $query)->count();

        $items = $query
            ->limit($limit)
            ->get()
            ->map(fn (ProfileReview $review) => self::formatReview($review))
            ->values()
            ->all();

        return [
            'total' => $total,
            'items' => $items,
            'has_more' => $total > $limit,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForViewer(int $reviewId, ?User $viewer): ?array
    {
        $review = ProfileReview::query()
            ->whereKey($reviewId)
            ->with([
                'author:id,name,role',
                'reply.author:id,name,role',
            ])
            ->withCount('reports')
            ->first();

        if (! $review || ! in_array($review->status, self::visibleStatuses($review->target_id, $viewer), true)) {
            return null;
        }

        return self::formatReview($review);
    }

    private static function baseQuery(int $targetUserId, ?User $viewer): Builder
    {
        return ProfileReview::query()
            ->where('target_id', $targetUserId)
            ->whereIn('status', self::visibleStatuses($targetUserId, $viewer))
            ->with([
                'author:id,name,role',
                'reply.author:id,name,role',
            ])
            ->withCount('reports')
            ->latest();
    }

    /**
     * @return array<int, string>
     */
    private static function visibleStatuses(int $targetUserId, ?User $viewer): array
    {
        if ($viewer && ((int) $viewer->id === $targetUserId || self::isAdmin($viewer))) {
            return ['published', 'reported', 'under_review', 'removed'];
        }

        return ['published'];
    }

    private static function isAdmin(User $user): bool
    {
        return in_array($user->role, ['admin', 'super_admin'], true)
            || strtolower((string) ($user->editor_role ?? '')) === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatReview(ProfileReview $review): array
    {
        return [
            'id' => $review->id,
            'author_id' => $review->author_id,
            'author_name' => $review->author?->name,
            'author_role' => $review->author_role,
            'target_id' => $review->target_id,
            'target_role' => $review->target_role,
            'relationship_type' => $review->relationship_type,
            'sentiment' => $review->sentiment,
            'body' => $review->body,
            'status' => $review->status,
            'reports_count' => $review->reports_count,
            'created_at' => optional($review->created_at)?->toISOString(),
            'updated_at' => optional($review->updated_at)?->toISOString(),
            'reply' => $review->reply ? [
                'id' => $review->reply->id,
                'author_id' => $review->reply->author_id,
                'author_name' => $review->reply->author?->name,
                'body' => $review->reply->body,
                'created_at' => optional($review->reply->created_at)?->toISOString(),
                'updated_at' => optional($review->reply->updated_at)?->toISOString(),
            ] : null,
        ];
    }
}
