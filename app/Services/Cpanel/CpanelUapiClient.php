<?php

namespace App\Services\Cpanel;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CpanelUapiClient
{
    public function call(string $module, string $function, array $query = []): array
    {
        $response = $this->request()->get(
            sprintf('/execute/%s/%s', $module, $function),
            $query,
        )->throw()->json();

        $result = $response['result'] ?? null;

        if (! is_array($result) || (int) ($result['status'] ?? 0) !== 1) {
            $errors = $result['errors'] ?? ['Unknown cPanel UAPI failure.'];
            $message = is_array($errors) ? implode(' ', array_filter($errors)) : (string) $errors;

            throw new RuntimeException('cPanel UAPI request failed: '.$message);
        }

        return $result;
    }

    private function request(): PendingRequest
    {
        $host = rtrim((string) config('pharmacy.cpanel.host'), '/');
        $username = (string) config('pharmacy.cpanel.username');
        $token = (string) config('pharmacy.cpanel.api_token');

        if ($host === '' || $username === '' || $token === '') {
            throw new RuntimeException('cPanel provisioning is enabled but its host, username, or API token is missing.');
        }

        return Http::baseUrl($host)
            ->withHeaders(['Authorization' => "cpanel {$username}:{$token}"])
            ->acceptJson()
            ->timeout((int) config('pharmacy.cpanel.timeout_seconds', 15))
            ->when(
                ! (bool) config('pharmacy.cpanel.verify_tls', true),
                fn (PendingRequest $request) => $request->withoutVerifying(),
            );
    }
}