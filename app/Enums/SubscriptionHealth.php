<?php

namespace App\Enums;

enum SubscriptionHealth: string
{
    case Healthy = 'healthy';
    case Trial = 'trial';
    case Expiring = 'expiring';
    case NoSubscription = 'no_subscription';
    case LicenseMissing = 'license_missing';
    case LicenseRevoked = 'license_revoked';
    case InactiveTenant = 'inactive_tenant';
    case NotStarted = 'not_started';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isOperational(): bool
    {
        return in_array($this, [
            self::Healthy,
            self::Trial,
            self::Expiring,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Trial => 'Trial',
            self::Expiring => 'Expiring soon',
            self::NoSubscription => 'No subscription',
            self::LicenseMissing => 'License missing',
            self::LicenseRevoked => 'License revoked',
            self::InactiveTenant => 'Pharmacy inactive',
            self::NotStarted => 'Not started',
            self::Suspended => 'Suspended',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }
}
