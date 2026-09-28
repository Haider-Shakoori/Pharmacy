<?php

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Services\Tenancy\CpanelTenantDatabaseProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CpanelTenantDatabaseProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_database_uses_the_full_cpanel_prefixed_name(): void
    {
        config([
            'pharmacy.cpanel.host' => 'https://cpanel.example.test:2083',
            'pharmacy.cpanel.username' => 'businessos',
            'pharmacy.cpanel.api_token' => 'test-token',
            'pharmacy.cpanel.database_prefix' => 'businessos_',
            'pharmacy.cpanel.database_user' => 'businessos_pharmacy',
            'pharmacy.cpanel.verify_tls' => true,
        ]);

        Http::fake(['*' => Http::response(['status' => 1, 'data' => [], 'errors' => null])]);
        $tenant = Tenant::query()->create();
        $expected = 'businessos_phm_'.Str::lower(Str::substr((string) $tenant->getTenantKey(), -16));

        app(CpanelTenantDatabaseProvisioner::class)->ensureDatabase($tenant);

        Http::assertSent(function (Request $request) use ($expected): bool {
            return str_contains($request->url(), '/execute/Mysql/create_database')
                && $request['name'] === $expected;
        });
    }
}
