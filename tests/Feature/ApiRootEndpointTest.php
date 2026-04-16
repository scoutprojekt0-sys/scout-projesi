<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiRootEndpointTest extends TestCase
{
    public function test_api_root_returns_service_metadata(): void
    {
        $response = $this->getJson('/api');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Scout API hazir.')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'Scout API')
            ->assertJsonPath('data.health_url', url('/up'))
            ->assertJsonPath('data.ping_url', url('/api/ping'));
    }
}
