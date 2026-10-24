<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginProductionSafetyTest extends TestCase
{
    public function test_the_demo_credentials_card_is_not_shown_outside_local_development(): void
    {
        // The test environment is "testing", not "local", so the card must be hidden.
        $this->get(route('admin.login'))->assertOk()
            ->assertDontSee('Demo Credentials')
            ->assertDontSee('admin@beyondsure.test')
            ->assertDontSee('fillDemoBtn');
    }
}
