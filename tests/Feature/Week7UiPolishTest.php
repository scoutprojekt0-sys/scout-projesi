<?php

namespace Tests\Feature;

use Tests\TestCase;

class Week7UiPolishTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_core_page_exposes_onboarding_and_live_regions(): void
    {
        $response = $this->get('/app/core');

        $response->assertOk();
        $response->assertSee('Skip to core content', false);
        $response->assertSee('Quick Start', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-live="polite"', false);
    }

    public function test_communication_page_exposes_onboarding_and_live_regions(): void
    {
        $response = $this->get('/app/communication');

        $response->assertOk();
        $response->assertSee('Skip to messaging content', false);
        $response->assertSee('Quick Start', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-live="polite"', false);
    }

    public function test_session_page_exposes_onboarding_and_live_regions(): void
    {
        $response = $this->get('/auth/sessions');

        $response->assertOk();
        $response->assertSee('Skip to session table', false);
        $response->assertSee('Quick Start', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-live="polite"', false);
    }
}
