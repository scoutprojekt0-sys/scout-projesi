<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiSupportEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_support_route_requires_authentication(): void
    {
        $this->postJson('/api/support/ai-chat', [
            'message' => 'sisteme giris yapamiyorum',
        ])->assertStatus(401);
    }

    public function test_ai_support_uses_openai_when_configured(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.support_model', 'gpt-5.4-mini');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'reply' => 'Giris hatasi icin sifreni yenileyip tekrar dene. Oturum acilmazsa destek talebi ac.',
                                    'category' => 'auth',
                                    'severity' => 'high',
                                    'should_open_ticket' => true,
                                    'ticket_subject' => 'Giris sorunu',
                                    'ticket_description' => 'Kullanici giris yapamiyor.',
                                    'suggested_actions' => [
                                        ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
                                        ['label' => 'Profili Kontrol Et', 'target' => 'profile'],
                                    ],
                                    'quick_replies' => [
                                        'Sifre sifirlama nasil yapilir?',
                                    ],
                                ], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:read']);

        $response = $this->postJson('/api/support/ai-chat', [
            'message' => 'sisteme giriş yapamıyorum',
            'history' => [
                [
                    'role' => 'assistant',
                    'content' => 'Merkez destek asistanina hos geldin.',
                ],
                [
                    'role' => 'user',
                    'content' => 'sisteme giriş yapamıyorum',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.category', 'auth')
            ->assertJsonPath('data.should_open_ticket', true)
            ->assertJsonPath('data.suggested_actions.0.target', 'support_ticket');

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.com/v1/responses'
                && ($payload['model'] ?? null) === 'gpt-5.4-mini'
                && ($payload['input'][1]['role'] ?? null) === 'assistant'
                && ($payload['input'][1]['content'] ?? null) === 'Merkez destek asistanina hos geldin.'
                && ($payload['input'][2]['role'] ?? null) === 'user'
                && ($payload['input'][2]['content'] ?? null) === 'sisteme giriş yapamıyorum';
        });
    }

    public function test_ai_support_falls_back_when_openai_is_not_configured(): void
    {
        config()->set('services.openai.api_key', '');

        $user = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($user, ['profile:read']);

        $response = $this->postJson('/api/support/ai-chat', [
            'message' => 'sisteme giris yapamiyorum',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.category', 'auth')
            ->assertJsonPath('data.ticket_subject', 'Giris sorunu');
    }
}
