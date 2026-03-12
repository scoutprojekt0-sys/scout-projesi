<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    use ApiResponds;

    public function getCategories(): JsonResponse
    {
        $categories = HelpCategory::query()
            ->with(['articles' => function ($query) {
                $query->where('is_published', true)
                    ->select(['id', 'category_id', 'title', 'slug', 'view_count', 'helpful_count', 'order']);
            }])
            ->orderBy('order')
            ->get();

        return $this->successResponse($categories, 'Yardim kategorileri hazir.');
    }

    public function getArticle(string $slug): JsonResponse
    {
        $article = HelpArticle::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $article->incrementViews();
        $article->load('category:id,name,slug');

        return $this->successResponse($article, 'Yardim makalesi hazir.');
    }

    public function getCategoryArticles(string $categorySlug): JsonResponse
    {
        $category = HelpCategory::query()->where('slug', $categorySlug)->firstOrFail();

        $articles = HelpArticle::query()
            ->where('category_id', $category->id)
            ->where('is_published', true)
            ->orderBy('order')
            ->paginate(10);

        return $this->successResponse($articles, 'Kategori makaleleri hazir.', 200, [
            'category' => $category,
        ]);
    }

    public function markArticleHelpful(string $slug): JsonResponse
    {
        $article = HelpArticle::query()->where('slug', $slug)->firstOrFail();
        $article->markHelpful();

        return $this->successResponse(null, 'Geri bildiriminiz kaydedildi.');
    }

    public function markArticleUnhelpful(string $slug): JsonResponse
    {
        $article = HelpArticle::query()->where('slug', $slug)->firstOrFail();
        $article->markUnhelpful();

        return $this->successResponse(null, 'Geri bildiriminiz kaydedildi.');
    }

    public function getFaq(Request $request): JsonResponse
    {
        $userType = $request->user()?->role ?? 'all';

        $faq = Faq::query()
            ->where('is_active', true)
            ->where(function ($query) use ($userType) {
                $query->where('user_type', $userType)
                    ->orWhere('user_type', 'all');
            })
            ->orderBy('order')
            ->paginate(15);

        return $this->paginatedListResponse($faq, 'Sik sorulan sorular hazir.');
    }

    public function markFaqHelpful(int $faqId): JsonResponse
    {
        $faq = Faq::query()->findOrFail($faqId);
        $faq->markHelpful();

        return $this->successResponse(null, 'Geri bildiriminiz kaydedildi.');
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $queryText = $validated['q'];

        $articles = HelpArticle::query()
            ->where('is_published', true)
            ->where(function ($query) use ($queryText) {
                $query->where('title', 'like', '%'.$queryText.'%')
                    ->orWhere('content', 'like', '%'.$queryText.'%')
                    ->orWhere('meta_description', 'like', '%'.$queryText.'%');
            })
            ->orderByDesc('helpful_count')
            ->orderBy('order')
            ->paginate(10);

        return $this->successResponse($articles, 'Arama sonuclari hazir.', 200, [
            'query' => $queryText,
        ]);
    }
}
