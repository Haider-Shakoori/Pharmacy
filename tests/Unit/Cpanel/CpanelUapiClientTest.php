<?php

namespace Tests\Unit\Cpanel;

use App\Services\Cpanel\CpanelUapiClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CpanelUapiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pharmacy.cpanel.host' => 'https://cpanel.example.test:2083',
            'pharmacy.cpanel.username' => 'businessos',
            'pharmacy.cpanel.api_token' => 'test-token',
            'pharmacy.cpanel.timeout_seconds' => 15,
            'pharmacy.cpanel.verify_tls' => true,
        ]);
    }

    public function test_accepts_direct_http_uapi_response_shape(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 1,
                'data' => [['database' => 'businessos_pharmacy']],
                'errors' => null,
            ]),
        ]);

        $result = app(CpanelUapiClient::class)->call('Mysql', 'list_databases');

        $this->assertSame(1, $result['status']);
        $this->assertSame('businessos_pharmacy', $result['data'][0]['database']);
    }

    public function test_accepts_wrapped_uapi_response_shape(): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => [
                    'status' => 1,
                    'data' => ['ok' => true],
                    'errors' => null,
                ],
            ]),
        ]);

        $result = app(CpanelUapiClient::class)->call('Mysql', 'list_databases');

        $this->assertTrue($result['data']['ok']);
    }

    public function test_direct_uapi_failure_is_reported(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 0,
                'data' => null,
                'errors' => ['Access denied.'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cPanel UAPI request failed: Access denied.');

        app(CpanelUapiClient::class)->call('Mysql', 'list_databases');
    }
}
