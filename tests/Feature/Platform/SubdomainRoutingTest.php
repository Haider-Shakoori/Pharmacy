<?php

namespace Tests\Feature\Platform;

use Tests\TestCase;

class SubdomainRoutingTest extends TestCase
{
    public function test_public_trial_request_uses_registration_subdomain(): void
    {
        $host = (string) config('pharmacy.registration_domain');

        $this->get('https://'.$host.'/')
            ->assertOk()
            ->assertSee('Request 7-day trial');

        $this->assertSame(
            'https://'.$host,
            route('trial.request'),
        );
    }

    public function test_platform_login_uses_platform_subdomain_without_legacy_prefix(): void
    {
        $host = (string) config('pharmacy.platform_domain');

        $this->get('https://'.$host.'/login')
            ->assertOk()
            ->assertSee('Platform');

        $this->assertSame(
            'https://'.$host.'/login',
            route('platform.login'),
        );
    }

    public function test_legacy_platform_path_remains_available_during_migration(): void
    {
        $host = (string) config('pharmacy.deployment_host');

        $this->get('https://'.$host.'/platform/login')
            ->assertOk();
    }
}
