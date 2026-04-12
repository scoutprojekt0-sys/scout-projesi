<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_ticket_routes_require_authentication(): void
    {
        $this->getJson('/api/support-tickets')->assertStatus(401);
        $this->postJson('/api/support-tickets')->assertStatus(401);
        $this->getJson('/api/support-tickets/1')->assertStatus(401);
        $this->postJson('/api/support-tickets/1/messages')->assertStatus(401);
        $this->postJson('/api/support-tickets/1/close')->assertStatus(401);
    }

    public function test_user_can_create_view_message_and_close_ticket(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($user, ['profile:read', 'profile:write']);

        $create = $this->postJson('/api/support-tickets', [
            'subject' => 'Login issue',
            'description' => 'Cannot access dashboard.',
            'priority' => 'high',
            'category' => 'technical',
        ]);

        $create
            ->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.title', 'Login issue');

        $ticketId = (int) $create->json('data.id');

        $this->getJson('/api/support-tickets')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $ticketId);

        $this->getJson('/api/support-tickets/'.$ticketId)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.title', 'Login issue')
            ->assertJsonMissingPath('data.assigned_to.email');

        $this->postJson('/api/support-tickets/'.$ticketId.'/messages', [
            'message' => 'Extra debugging details.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.message', 'Extra debugging details.')
            ->assertJsonMissingPath('data.user.email');

        $this->postJson('/api/support-tickets/'.$ticketId.'/close')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'status' => 'closed',
        ]);
    }

    public function test_user_cannot_view_or_close_another_users_ticket(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $other = User::factory()->create(['role' => 'manager']);

        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'title' => 'Owner Ticket',
            'description' => 'Private',
            'category' => 'general',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        Sanctum::actingAs($other, ['profile:read', 'profile:write']);

        $this->getJson('/api/support-tickets/'.$ticket->id)->assertStatus(404);
        $this->postJson('/api/support-tickets/'.$ticket->id.'/close')->assertStatus(404);
    }
}
