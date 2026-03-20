<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ProfileReview;
use App\Models\ProfileReviewReply;
use App\Models\ProfileReviewReport;
use App\Models\User;
use App\Support\ProfileReviewData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileReviewController extends Controller
{
    use ApiResponds;

    private const RELATIONSHIP_TYPES = [
        'birlikte_calistik',
        'rakiptik',
        'izledim',
        'temsil_sureci',
        'kulup_sureci',
        'teknik_ekip',
    ];

    private const SENTIMENTS = ['olumlu', 'notr', 'dikkat'];

    private const REPORT_REASONS = [
        'hakaret',
        'iftira',
        'kisisel_saldiri',
        'spam',
        'yaniltici',
        'diger',
    ];

    private const MODERATION_STATUSES = ['published', 'reported', 'under_review', 'removed'];

    public function index(Request $request, int $userId): JsonResponse
    {
        $this->findTargetUser($userId);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $reviews = ProfileReviewData::paginateForTarget(
            $userId,
            $request->user(),
            (int) ($validated['per_page'] ?? 20)
        );

        return $this->successResponse($reviews, 'Profil yorumlari hazir.');
    }

    public function store(Request $request, int $userId): JsonResponse
    {
        $author = $request->user();
        $target = $this->findTargetUser($userId);

        if ((int) $author->id === (int) $target->id) {
            return $this->errorResponse('Kendi profilinize yorum birakamazsiniz.', Response::HTTP_UNPROCESSABLE_ENTITY, 'self_review_not_allowed');
        }

        $validated = $request->validate([
            'relationship_type' => ['required', 'in:'.implode(',', self::RELATIONSHIP_TYPES)],
            'sentiment' => ['required', 'in:'.implode(',', self::SENTIMENTS)],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $existingReview = ProfileReview::query()
            ->where('author_id', $author->id)
            ->where('target_id', $target->id)
            ->first();

        if ($existingReview && in_array($existingReview->status, ['under_review', 'removed'], true)) {
            return $this->errorResponse('Bu yorum su anda guncellenemez.', Response::HTTP_UNPROCESSABLE_ENTITY, 'review_locked');
        }

        $review = ProfileReview::query()->updateOrCreate(
            [
                'author_id' => $author->id,
                'target_id' => $target->id,
            ],
            [
                'author_role' => $author->role,
                'target_role' => $target->role,
                'relationship_type' => $validated['relationship_type'],
                'sentiment' => $validated['sentiment'],
                'body' => trim($validated['body']),
                'status' => $existingReview?->status ?? 'published',
            ]
        );

        $message = $review->wasRecentlyCreated
            ? 'Profil yorumu yayinlandi.'
            : 'Profil yorumu guncellendi.';

        return $this->successResponse(
            ProfileReviewData::findForViewer($review->id, $author),
            $message,
            $review->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK
        );
    }

    public function reply(Request $request, int $reviewId): JsonResponse
    {
        $review = ProfileReview::query()->findOrFail($reviewId);
        $user = $request->user();

        if ((int) $review->target_id !== (int) $user->id) {
            return $this->errorResponse('Bu yoruma sadece yorumun sahibi yanit verebilir.', Response::HTTP_FORBIDDEN, 'forbidden_reply');
        }

        if ($review->status === 'removed') {
            return $this->errorResponse('Kaldirilan yoruma yanit verilemez.', Response::HTTP_UNPROCESSABLE_ENTITY, 'review_removed');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        ProfileReviewReply::query()->updateOrCreate(
            ['review_id' => $review->id],
            [
                'author_id' => $user->id,
                'body' => trim($validated['body']),
            ]
        );

        return $this->successResponse(
            ProfileReviewData::findForViewer($review->id, $user),
            'Yanit kaydedildi.'
        );
    }

    public function report(Request $request, int $reviewId): JsonResponse
    {
        $review = ProfileReview::query()->findOrFail($reviewId);
        $user = $request->user();

        $validated = $request->validate([
            'reason' => ['required', 'in:'.implode(',', self::REPORT_REASONS)],
        ]);

        $alreadyReported = ProfileReviewReport::query()
            ->where('review_id', $review->id)
            ->where('reported_by', $user->id)
            ->exists();

        if ($alreadyReported) {
            return $this->errorResponse('Bu yorumu zaten raporladiniz.', Response::HTTP_UNPROCESSABLE_ENTITY, 'already_reported');
        }

        ProfileReviewReport::query()->create([
            'review_id' => $review->id,
            'reported_by' => $user->id,
            'reason' => $validated['reason'],
        ]);

        if ($review->status === 'published') {
            $review->update(['status' => 'reported']);
        }

        return $this->successResponse(null, 'Yorum raporlandi. Incelemeye alinacaktir.', Response::HTTP_CREATED);
    }

    public function moderate(Request $request, int $reviewId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::MODERATION_STATUSES)],
        ]);

        $review = ProfileReview::query()->findOrFail($reviewId);
        $review->update([
            'status' => $validated['status'],
        ]);

        return $this->successResponse(
            ProfileReviewData::findForViewer($review->id, $request->user()),
            'Yorum durumu guncellendi.'
        );
    }

    private function findTargetUser(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }
}
