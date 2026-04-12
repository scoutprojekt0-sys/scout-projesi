<?php

namespace Tests\Feature;

use App\Models\Lawyer;
use App\Models\ProfileReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileReviewEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_only_publicly_visible_profile_reviews(): void
    {
        $target = User::factory()->create(['role' => 'player']);
        $author = User::factory()->create(['role' => 'scout']);

        ProfileReview::query()->create([
            'author_id' => $author->id,
            'author_role' => $author->role,
            'target_id' => $target->id,
            'target_role' => $target->role,
            'relationship_type' => 'izledim',
            'sentiment' => 'olumlu',
            'body' => 'Sahada disiplinli ve karar alma kalitesi yuksek gorundu.',
            'status' => 'published',
        ]);

        ProfileReview::query()->create([
            'author_id' => User::factory()->create(['role' => 'manager'])->id,
            'author_role' => 'manager',
            'target_id' => $target->id,
            'target_role' => $target->role,
            'relationship_type' => 'birlikte_calistik',
            'sentiment' => 'dikkat',
            'body' => 'Bu yorum moderasyon altinda ve herkese acik olmamali.',
            'status' => 'under_review',
        ]);

        $this->getJson('/api/profiles/'.$target->id.'/reviews')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.status', 'published');
    }

    public function test_authenticated_user_can_create_and_update_single_review_per_target(): void
    {
        $author = User::factory()->create(['role' => 'coach']);
        $target = User::factory()->create(['role' => 'player']);

        Sanctum::actingAs($author, ['profile:read', 'profile:write']);

        $create = $this->postJson('/api/profiles/'.$target->id.'/reviews', [
            'relationship_type' => 'birlikte_calistik',
            'sentiment' => 'olumlu',
            'body' => 'Antrenman disiplini yuksek, taktik uygulama seviyesi guclu.',
        ]);

        $create
            ->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.author_id', $author->id)
            ->assertJsonPath('data.target_id', $target->id);

        $update = $this->postJson('/api/profiles/'.$target->id.'/reviews', [
            'relationship_type' => 'teknik_ekip',
            'sentiment' => 'notr',
            'body' => 'Iletisimi iyi, ancak karar hizinda daha fazla gelisim alani var.',
        ]);

        $update
            ->assertOk()
            ->assertJsonPath('data.relationship_type', 'teknik_ekip')
            ->assertJsonPath('data.sentiment', 'notr');

        $this->assertDatabaseCount('profile_reviews', 1);

        Sanctum::actingAs(User::factory()->create(['role' => 'scout']), ['profile:read']);

        $this->getJson('/api/players/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.reviews.total', 1)
            ->assertJsonPath('data.reviews.items.0.relationship_type', 'teknik_ekip');
    }

    public function test_only_target_user_can_reply_to_profile_review(): void
    {
        $author = User::factory()->create(['role' => 'manager']);
        $target = User::factory()->create(['role' => 'player']);
        $other = User::factory()->create(['role' => 'coach']);

        $review = ProfileReview::query()->create([
            'author_id' => $author->id,
            'author_role' => $author->role,
            'target_id' => $target->id,
            'target_role' => $target->role,
            'relationship_type' => 'temsil_sureci',
            'sentiment' => 'olumlu',
            'body' => 'Profesyonel iletisim kurdu ve sureci zamaninda yonetti.',
            'status' => 'published',
        ]);

        Sanctum::actingAs($other, ['profile:write']);

        $this->postJson('/api/profile-reviews/'.$review->id.'/reply', [
            'body' => 'Bu yanit kabul edilmemeli.',
        ])->assertStatus(403);

        Sanctum::actingAs($target, ['profile:read', 'profile:write']);

        $this->postJson('/api/profile-reviews/'.$review->id.'/reply', [
            'body' => 'Geri bildirim icin tesekkur ederim.',
        ])
            ->assertOk()
            ->assertJsonPath('data.reply.author_id', $target->id)
            ->assertJsonPath('data.reply.body', 'Geri bildirim icin tesekkur ederim.');
    }

    public function test_reported_review_is_hidden_from_public_profile_views(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $lawyerUser = User::factory()->create(['role' => 'scout']);
        $reporter = User::factory()->create(['role' => 'player']);

        $lawyer = Lawyer::query()->create([
            'user_id' => $lawyerUser->id,
            'license_number' => 'BARO-12345',
            'specialization' => 'sports_law',
        ]);

        $review = ProfileReview::query()->create([
            'author_id' => $owner->id,
            'author_role' => $owner->role,
            'target_id' => $lawyerUser->id,
            'target_role' => $lawyerUser->role,
            'relationship_type' => 'kulup_sureci',
            'sentiment' => 'olumlu',
            'body' => 'Sozlesme surecinde net, hizli ve cozum odakli destek verdi.',
            'status' => 'published',
        ]);

        Sanctum::actingAs($reporter, ['profile:write']);

        $this->postJson('/api/profile-reviews/'.$review->id.'/report', [
            'reason' => 'yaniltici',
        ])
            ->assertStatus(201)
            ->assertJsonPath('ok', true);

        $this->postJson('/api/profile-reviews/'.$review->id.'/report', [
            'reason' => 'spam',
        ])->assertStatus(422);

        $this->assertDatabaseHas('profile_reviews', [
            'id' => $review->id,
            'status' => 'reported',
        ]);

        $this->getJson('/api/lawyers/'.$lawyer->id)
            ->assertOk()
            ->assertJsonPath('data.reviews.total', 0)
            ->assertJsonPath('data.reviews.items', []);
    }

    public function test_target_user_can_still_see_reported_review_in_private_context(): void
    {
        $author = User::factory()->create(['role' => 'manager']);
        $target = User::factory()->create(['role' => 'player']);

        $review = ProfileReview::query()->create([
            'author_id' => $author->id,
            'author_role' => $author->role,
            'target_id' => $target->id,
            'target_role' => $target->role,
            'relationship_type' => 'kulup_sureci',
            'sentiment' => 'dikkat',
            'body' => 'Bu yorum raporlanmis olsa da hedef kullanici tarafindan gorulebilmeli.',
            'status' => 'reported',
        ]);

        Sanctum::actingAs($target, ['profile:read']);

        $this->getJson('/api/profiles/'.$target->id.'/reviews')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $review->id)
            ->assertJsonPath('data.data.0.status', 'reported');
    }
}
