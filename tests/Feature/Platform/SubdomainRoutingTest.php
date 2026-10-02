<?php

namespace Tests\Feature\Platform;

use Tests\TestCase;

class SubdomainRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_trial_request_uses_registration_subdomain(): void
    {
        $host = (string) config('pharmacy.registration_domain');

        $this->get('https://'.$host.'/')
            ->assertOk()
            ->assertSee('Request 7-day trial');

        $url = route('trial.request');
        $this->assertSame($host, parse_url($url, PHP_URL_HOST));
        $this->assertSame('/', parse_url($url, PHP_URL_PATH) ?: '/');
    }

    public function test_platform_login_uses_platform_subdomain_without_legacy_prefix(): void
    {
        $host = (string) config('pharmacy.platform_domain');

        $this->get('https://'.$host.'/login')
            ->assertOk()
            ->assertSee('Platform');

        $url = route('platform.login');
        $this->assertSame($host, parse_url($url, PHP_URL_HOST));
        $this->assertSame('/login', parse_url($url, PHP_URL_PATH));
    }

    public function test_api_readiness_uses_reserved_api_subdomain(): void
    {
        $host = (string) config('pharmacy.api_domain');

        $this->get('https://'.$host.'/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }

    public function test_legacy_platform_path_remains_available_during_migration(): void
    {
        $host = (string) config('pharmacy.deployment_host');

        $this->get('https://'.$host.'/platform/login')
            ->assertOk();
    }
}
