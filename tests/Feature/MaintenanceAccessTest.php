<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->artisan('up');

        parent::tearDown();
    }

    public function test_auth_login_endpoint_remains_accessible_during_maintenance(): void
    {
        $this->artisan('down');

        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Secret123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'auth_invalid_credentials');
    }

    public function test_health_endpoint_remains_accessible_during_maintenance(): void
    {
        $this->artisan('down');

        $this->get('/up')->assertOk();
    }
}
