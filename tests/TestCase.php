<?php

namespace Tests;

use App\Models\Business;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

abstract class TestCase extends BaseTestCase
{
    /** @var array<int, string> */
    private array $tenantDatabaseFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('https://'.config('pharmacy.deployment_host'));
    }

    protected function createTenant(array $attributes = []): Tenant
    {
        $slug = (string) ($attributes['slug'] ?? Str::lower(Str::random(10)));
        $name = (string) ($attributes['name'] ?? Str::headline($slug));

        $tenant = Tenant::query()->create([
            'status' => $attributes['status'] ?? 'active',
            'provisioning_status' => 'testing',
        ]);

        Business::query()->create([
            'tenant_id' => $tenant->id,
            'pharmacy_name' => $name,
            'slug' => $slug,
            'contact_person' => $attributes['contact_person'] ?? 'Test Owner',
            'phone_whatsapp' => $attributes['phone_whatsapp'] ?? '+93000000000',
            'location' => $attributes['location'] ?? 'Afghanistan',
            'owner_email' => $attributes['owner_email'] ?? "owner@{$slug}.test",
            'billing_currency' => $attributes['currency'] ?? 'AFN',
            'default_timezone' => $attributes['timezone'] ?? 'Asia/Kabul',
            'default_locale' => $attributes['locale'] ?? 'en',
        ]);

        CreateDatabase::dispatchSync($tenant);
        MigrateDatabase::dispatchSync($tenant);

        $this->tenantDatabaseFiles[] = database_path($tenant->database()->getName());
        $tenant->domains()->create([
            'domain' => $slug.'.'.config('pharmacy.deployment_host'),
        ]);

        return $tenant->fresh(['business', 'domains']);
    }

    protected function tenantHost(Tenant $tenant): string
    {
        return (string) $tenant->domains()->value('domain');
    }

    protected function onTenantDomain(Tenant $tenant): static
    {
        $host = $this->tenantHost($tenant);

        URL::forceRootUrl('https://'.$host);

        $this->withServerVariables([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
            'HTTPS' => 'on',
        ]);

        return $this->withHeader('Host', $host);
    }

    protected function tearDown(): void
    {
        URL::forceRootUrl(null);

        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        DB::purge('tenant');

        parent::tearDown();

        foreach ($this->tenantDatabaseFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
