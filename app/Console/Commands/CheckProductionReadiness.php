<?php

namespace App\Console\Commands;

use App\Services\Production\ProductionReadinessService;
use Illuminate\Console\Command;

class CheckProductionReadiness extends Command
{
    protected $signature = 'pharmacy:production:check
        {--remote : Verify read-only cPanel UAPI connectivity}
        {--strict : Treat warnings as release blockers}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Check whether BusinessOS Pharmacy is safe to deploy to production.';

    public function handle(ProductionReadinessService $readiness): int
    {
        $result = $readiness->check((bool) $this->option('remote'));

        if ($this->option('json')) {
            $this->line(json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return $this->exitCode($result);
        }

        $this->table(
            ['Check', 'Status', 'Message'],
            collect($result['checks'])
                ->map(fn (array $check): array => [
                    $check['name'],
                    strtoupper($check['status']),
                    $check['message'],
                ])
                ->all(),
        );

        $ready = $this->option('strict')
            ? $result['strict_ready']
            : $result['ready'];

        $ready
            ? $this->info('Production readiness gate passed.')
            : $this->error('Production readiness gate failed.');

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    private function exitCode(array $result): int
    {
        $ready = $this->option('strict')
            ? $result['strict_ready']
            : $result['ready'];

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
