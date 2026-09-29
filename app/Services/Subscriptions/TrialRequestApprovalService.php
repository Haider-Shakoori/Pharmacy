<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TrialRequest;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrialRequestApprovalService
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly TrialProvisioner $trials,
    ) {}

    /**
     * @return array{request: TrialRequest, tenant: Tenant, license_key: ?string}
     */
    public function approve(TrialRequest $trialRequest, PlatformAdmin $reviewer): array
    {
        $trialRequest->refresh();

        if (! in_array($trialRequest->status, ['pending', 'failed', 'provisioning'], true)) {
            throw ValidationException::withMessages([
                'approval' => 'This trial request can no longer be approved.',
            ]);
        }

        $tenant = $trialRequest->tenant;
        $licenseKey = null;

        try {
            if ($tenant === null) {
                [$tenant] = $this->provisioning->provision([
                    'name' => $trialRequest->pharmacy_name,
                    'slug' => $trialRequest->requested_slug,
                    'contact_person' => $trialRequest->owner_name,
                    'phone_whatsapp' => $trialRequest->phone_whatsapp,
                    'location' => $trialRequest->location,
                    'timezone' => 'Asia/Kabul',
                    'currency' => 'AFN',
                    'locale' => $trialRequest->preferred_locale,
                    'owner_name' => $trialRequest->owner_name,
                    'owner_email' => $trialRequest->owner_email,
                    'owner_password' => Crypt::decryptString($trialRequest->owner_password_ciphertext),
                ]);

                $trialRequest->forceFill(['tenant_id' => $tenant->id])->save();
            } elseif ($tenant->provisioning_status !== 'application_ready') {
                [$tenant] = $this->provisioning->resume($tenant);
            }

            if ($tenant->provisioning_status !== 'application_ready') {
                $trialRequest->forceFill([
                    'status' => 'provisioning',
                    'tenant_id' => $tenant->id,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                    'failure_reason' => null,
                    'owner_password_ciphertext' => null,
                ])->save();

                return [
                    'request' => $trialRequest->fresh(),
                    'tenant' => $tenant,
                    'license_key' => null,
                ];
            }

            $tenant->load('subscription.license');

            if ($tenant->subscription === null) {
                $licenseKey = $this->trials->provision($tenant);
            } elseif ($tenant->subscription->status !== SubscriptionStatus::Trial) {
                throw ValidationException::withMessages([
                    'approval' => 'The provisioned pharmacy already has a non-trial subscription.',
                ]);
            }

            $trialRequest->forceFill([
                'status' => 'approved',
                'tenant_id' => $tenant->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'failure_reason' => null,
                'owner_password_ciphertext' => null,
            ])->save();

            return [
                'request' => $trialRequest->fresh(),
                'tenant' => $tenant->fresh(['business', 'domains', 'subscription.license']),
                'license_key' => $licenseKey,
            ];
        } catch (Throwable $exception) {
            if ($tenant === null) {
                $tenantId = Business::query()
                    ->where('slug', $trialRequest->requested_slug)
                    ->value('tenant_id');

                if (is_string($tenantId) && $tenantId !== '') {
                    $trialRequest->tenant_id = $tenantId;
                }
            }

            $trialRequest->forceFill([
                'status' => 'failed',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'failure_reason' => str($exception->getMessage())->limit(2000),
            ])->save();

            throw $exception;
        }
    }
}
