<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HelpEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_browse_help_categories_articles_and_search(): void
    {
        $category = HelpCategory::query()->create([
            'name' => 'Hesap',
            'slug' => 'hesap',
            'order' => 1,
        ]);

        HelpArticle::query()->create([
            'category_id' => $category->id,
            'title' => 'Sifre nasil yenilenir',
            'slug' => 'sifre-nasil-yenilenir',
            'content' => 'Sifrenizi ayarlar ekranindan yenileyebilirsiniz.',
            'meta_description' => 'Sifre yardim makalesi',
            'is_published' => true,
            'order' => 1,
        ]);

        $this->getJson('/api/help/categories')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.slug', 'hesap')
            ->assertJsonPath('data.0.articles.0.slug', 'sifre-nasil-yenilenir');

        $this->getJson('/api/help/categories/hesap/articles')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('category.slug', 'hesap')
            ->assertJsonPath('data.data.0.slug', 'sifre-nasil-yenilenir');

        $this->getJson('/api/help/articles/sifre-nasil-yenilenir')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.slug', 'sifre-nasil-yenilenir');

        $this->getJson('/api/help/search?q=sifre')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('query', 'sifre')
            ->assertJsonPath('data.data.0.slug', 'sifre-nasil-yenilenir');
    }

    public function test_public_can_mark_article_and_faq_helpful(): void
    {
        $category = HelpCategory::query()->create([
            'name' => 'Profil',
            'slug' => 'profil',
            'order' => 1,
        ]);

        $article = HelpArticle::query()->create([
            'category_id' => $category->id,
            'title' => 'Profil duzenleme',
            'slug' => 'profil-duzenleme',
            'content' => 'Profilinizi guncelleyebilirsiniz.',
            'is_published' => true,
        ]);

        $faq = Faq::query()->create([
            'question' => 'Profilim neden gorunmuyor?',
            'answer' => 'Gizlilik ayarlarinizi kontrol edin.',
            'user_type' => 'all',
            'topic' => 'profile',
            'is_active' => true,
        ]);

        $this->postJson('/api/help/articles/profil-duzenleme/helpful')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/help/articles/profil-duzenleme/unhelpful')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/help/faq/'.$faq->id.'/helpful')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('help_articles', [
            'id' => $article->id,
            'helpful_count' => 1,
            'unhelpful_count' => 1,
        ]);

        $this->assertDatabaseHas('faq', [
            'id' => $faq->id,
            'helpful_count' => 1,
        ]);
    }

    public function test_faq_filters_by_authenticated_user_role(): void
    {
        Faq::query()->create([
            'question' => 'Genel soru',
            'answer' => 'Genel cevap',
            'user_type' => 'all',
            'topic' => 'other',
            'is_active' => true,
            'order' => 1,
        ]);

        Faq::query()->create([
            'question' => 'Scout sorusu',
            'answer' => 'Scout cevabi',
            'user_type' => 'scout',
            'topic' => 'search',
            'is_active' => true,
            'order' => 2,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'scout']), ['profile:read']);

        $this->getJson('/api/help/faq')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 2);
    }
}
