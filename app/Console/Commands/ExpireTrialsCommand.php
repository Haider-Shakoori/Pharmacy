<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Console\Command;

class ExpireTrialsCommand extends Command
{
    protected $signature = 'subscriptions:expire-trials';

    protected $description = 'Expire elapsed pharmacy trials and revoke their licenses.';

    public function handle(LicenseKeyService $licenseKeys): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($licenseKeys, &$count): void {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => SubscriptionStatus::Expired]);
                    $licenseKeys->revoke($subscription);
                    $count++;
                }
            }, column: 'id');

        $this->info("Expired {$count} trial subscription(s).");

        return self::SUCCESS;
    }
}
