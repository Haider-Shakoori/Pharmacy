<?php

namespace App\Services\Production;

use App\Models\PlatformAdmin;
use App\Services\Cpanel\CpanelUapiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

class ProductionReadinessService
{
    public function __construct(
        private readonly CpanelUapiClient $cpanel,
    ) {}

    public function check(bool $remote = false): array
    {
        $checks = [];

        $this->add($checks, 'environment.production', config('app.env') === 'production', 'APP_ENV is production.', 'APP_ENV must be production.');
        $this->add($checks, 'environment.debug', ! (bool) config('app.debug'), 'APP_DEBUG is disabled.', 'APP_DEBUG must be false in production.');

        $appUrl = (string) config('app.url');
        $deploymentHost = (string) config('pharmacy.deployment_host');
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        $this->add($checks, 'environment.https', str_starts_with($appUrl, 'https://'), 'APP_URL uses HTTPS.', 'APP_URL must use HTTPS.');
        $this->add(
            $checks,
            'environment.host',
            $deploymentHost !== '' && is_string($appHost) && hash_equals($deploymentHost, $appHost),
            'APP_URL matches the pharmacy deployment host.',
            'APP_URL host must match PHARMACY_DEPLOYMENT_HOST.',
        );
        $this->add($checks, 'environment.app_key', $this->validApplicationKey(), 'APP_KEY is configured.', 'APP_KEY is missing or invalid.');

        $this->add(
            $checks,
            'security.session',
            (bool) config('session.encrypt')
                && (bool) config('session.secure')
                && (bool) config('session.http_only')
                && in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Session encryption and secure cookie controls are enabled.',
            'Encrypted, Secure, HttpOnly and SameSite session controls are required.',
        );
        $this->add($checks, 'security.hsts', (bool) config('pharmacy.security.hsts_enabled'), 'HSTS is enabled.', 'HSTS must be enabled in production.');
        $this->add(
            $checks,
            'security.signing_keys',
            $this->validSigningKeyPair(),
            'Ed25519 license signing key pair is valid and matched.',
            'A valid matched Ed25519 license signing key pair is required.',
        );

        $this->add($checks, 'database.central', $this->centralDatabaseReachable(), 'Central database is reachable.', 'Central database is not reachable.');

        $centralDriver = (string) config('database.connections.central.driver');
        $this->add(
            $checks,
            'database.production_driver',
            in_array($centralDriver, ['mysql', 'mariadb'], true),
            'Central database uses a production MySQL-compatible driver.',
            'Central database is not using MySQL/MariaDB.',
            'warning',
        );

        $this->add($checks, 'platform.admin', $this->hasPlatformAdmin(), 'At least one active platform administrator exists.', 'No active platform administrator exists.');

        $this->add(
            $checks,
            'provisioning.driver',
            config('pharmacy.provisioning.driver') === 'cpanel',
            'Tenant provisioning uses the cPanel production driver.',
            'TENANCY_DB_PROVISIONER must be cpanel for hosted production.',
        );
        $this->add(
            $checks,
            'provisioning.cpanel_config',
            $this->completeCpanelConfiguration(),
            'Required cPanel provisioning configuration is present.',
            'cPanel host, username, token and database user must be configured.',
        );
        $this->add(
            $checks,
            'provisioning.cpanel_tls',
            (bool) config('pharmacy.cpanel.verify_tls'),
            'cPanel TLS verification is enabled.',
            'CPANEL_VERIFY_TLS must remain enabled in production.',
        );

        if ($remote) {
            $this->add(
                $checks,
                'provisioning.cpanel_reachable',
                $this->cpanelReachable(),
                'cPanel UAPI is reachable with the configured token.',
                'cPanel UAPI cannot be reached with the configured token.',
            );
        }

        $backupRoot = (string) config('backup.root');
        $this->add($checks, 'backup.schedule', (bool) config('backup.schedule_enabled'), 'Scheduled backups are enabled.', 'Scheduled production backups are disabled.');
        $this->add(
            $checks,
            'backup.private_path',
            $backupRoot !== '' && ! $this->insidePublicPath($backupRoot),
            'Backup root is outside the public web directory.',
            'BACKUP_ROOT must be outside the public web directory.',
        );
        $this->add(
            $checks,
            'filesystem.storage',
            is_writable(storage_path()) && is_writable(bootstrap_path('cache')),
            'Laravel storage and bootstrap cache directories are writable.',
            'Laravel storage/bootstrap cache directories must be writable.',
        );
        $this->add($checks, 'monitoring.liveness', $this->routeExists('up'), 'Liveness endpoint /up is registered.', 'Liveness endpoint /up is not registered.');
        $this->add($checks, 'monitoring.readiness', $this->routeExists('ready'), 'Readiness endpoint /ready is registered.', 'Readiness endpoint /ready is not registered.');

        $bootstrapPassword = (string) config('pharmacy.platform.bootstrap_admin.password');
        $this->add(
            $checks,
            'security.bootstrap_password',
            $bootstrapPassword === '',
            'Platform bootstrap password is not retained in runtime configuration.',
            'Clear PLATFORM_ADMIN_PASSWORD after the initial administrator is provisioned.',
            'warning',
        );

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['status'] !== 'fail'),
            'strict_ready' => collect($checks)->every(fn (array $check): bool => $check['status'] === 'pass'),
            'checks' => $checks,
        ];
    }

    private function validApplicationKey(): bool
    {
        $key = (string) config('app.key');

        if ($key === '') {
            return false;
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return is_string($decoded) && in_array(strlen($decoded), [16, 32], true);
        }

        return in_array(strlen($key), [16, 32], true);
    }

    private function validSigningKeyPair(): bool
    {
        if (! function_exists('sodium_crypto_sign_publickey_from_secretkey')) {
            return false;
        }

        $secret = base64_decode((string) config('pharmacy.license.signing_private_key'), true);
        $public = base64_decode((string) config('pharmacy.license.signing_public_key'), true);

        if (! is_string($secret)
            || ! is_string($public)
            || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
            || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return hash_equals($public, sodium_crypto_sign_publickey_from_secretkey($secret));
    }

    private function centralDatabaseReachable(): bool
    {
        try {
            DB::connection('central')->selectOne('select 1 as ready');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasPlatformAdmin(): bool
    {
        try {
            return PlatformAdmin::query()->where('is_active', true)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function completeCpanelConfiguration(): bool
    {
        return collect([
            config('pharmacy.cpanel.host'),
            config('pharmacy.cpanel.username'),
            config('pharmacy.cpanel.api_token'),
            config('pharmacy.cpanel.database_user'),
        ])->every(fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }

    private function cpanelReachable(): bool
    {
        if (! $this->completeCpanelConfiguration()) {
            return false;
        }

        try {
            $this->cpanel->call('Mysql', 'list_databases');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function insidePublicPath(string $path): bool
    {
        $public = rtrim(public_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $normalized = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($normalized, $public);
    }

    private function routeExists(string $uri): bool
    {
        return collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route): bool => $route->uri() === $uri);
    }

    private function add(
        array &$checks,
        string $name,
        bool $passed,
        string $success,
        string $failure,
        string $failureStatus = 'fail',
    ): void {
        $checks[] = [
            'name' => $name,
            'status' => $passed ? 'pass' : $failureStatus,
            'message' => $passed ? $success : $failure,
        ];
    }
}
