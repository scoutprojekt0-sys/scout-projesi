<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicContactMessageEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contact_submission_does_not_echo_sensitive_storage_fields(): void
    {
        $this->postJson('/api/public/contact-messages', [
            'name' => 'Guest User',
            'email' => 'guest@openai.com',
            'message' => 'Need help with the platform contact form.',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.name', 'Guest User')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.ip_address')
            ->assertJsonMissingPath('data.user_agent');
    }

    public function test_admin_contact_message_index_includes_email_but_hides_ip_and_user_agent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->postJson('/api/public/contact-messages', [
            'name' => 'Guest User',
            'email' => 'guest@openai.com',
            'message' => 'Need help with the platform contact form.',
        ])->assertCreated();

        Sanctum::actingAs($admin, $admin->tokenAbilities());

        $this->getJson('/api/admin/contact-messages')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.data.0.email', 'guest@openai.com')
            ->assertJsonMissingPath('data.data.0.ip_address')
            ->assertJsonMissingPath('data.data.0.user_agent');
    }
}
