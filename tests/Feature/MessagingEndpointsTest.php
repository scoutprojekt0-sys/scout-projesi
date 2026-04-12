<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_routes_require_authentication(): void
    {
        $this->postJson('/api/messages', [])->assertStatus(401);
        $this->getJson('/api/messages/inbox')->assertStatus(401);
        $this->getJson('/api/messages/sent')->assertStatus(401);
        $this->postJson('/api/messages/read-all')->assertStatus(401);
        $this->patchJson('/api/messages/1/read')->assertStatus(401);
    }

    public function test_user_can_send_list_read_and_archive_messages(): void
    {
        $sender = User::factory()->create(['role' => 'scout', 'name' => 'Sender']);
        $recipient = User::factory()->create(['role' => 'player', 'name' => 'Recipient']);

        Sanctum::actingAs($sender, ['contact:write', 'contact:read']);

        $this->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'subject' => 'Hello',
            'message' => 'Test message body',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.subject', 'Hello');

        $this->getJson('/api/messages/sent')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.recipient_name', 'Recipient');

        Sanctum::actingAs($recipient, ['contact:read', 'contact:write']);

        $this->getJson('/api/messages/inbox?unread_only=1')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.sender_name', 'Sender')
            ->assertJsonPath('data.data.0.is_read', 0);

        $messageId = $this->getJson('/api/messages/inbox')->json('data.data.0.id');

        $this->patchJson('/api/messages/'.$messageId.'/read')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'read');

        $this->postJson('/api/messages/'.$messageId.'/archive')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('contacts', [
            'id' => $messageId,
            'status' => 'archived',
        ]);
    }

    public function test_user_can_mark_all_messages_as_read(): void
    {
        $sender = User::factory()->create(['role' => 'scout']);
        $recipient = User::factory()->create(['role' => 'player']);

        Sanctum::actingAs($sender, ['contact:write']);
        $this->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'message' => 'One',
        ])->assertCreated();
        $this->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'message' => 'Two',
        ])->assertCreated();

        Sanctum::actingAs($recipient, ['contact:read', 'contact:write']);

        $this->postJson('/api/messages/read-all')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/messages/inbox')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_can_search_message_recipients_across_roles(): void
    {
        $team = User::factory()->create(['role' => 'team', 'name' => 'Kulup']);
        $scout = User::factory()->create(['role' => 'scout', 'name' => 'Scout Hakan', 'email' => 'scout.hakan@example.com']);
        $player = User::factory()->create(['role' => 'player', 'name' => 'Oyuncu Hakan', 'email' => 'oyuncu.hakan@example.com']);

        Sanctum::actingAs($team, ['contact:read']);

        $this->getJson('/api/contacts/recipients/search?q=Scout Hakan')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.id', $scout->id)
            ->assertJsonPath('data.0.role', 'scout')
            ->assertJsonMissingPath('data.0.email');

        $this->getJson('/api/contacts/recipients/search?q=@example.com')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(0, 'data');
    }
}
