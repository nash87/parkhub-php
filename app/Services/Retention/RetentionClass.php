<?php

declare(strict_types=1);

namespace App\Services\Retention;

/**
 * GDPR / DSGVO data-retention classes.
 *
 * Each case maps to a named category of data with a default TTL and, for
 * legally-mandated classes, a statutory minimum that cannot be overridden
 * below the legal floor.
 *
 * Cross-repo contract: values must stay byte-identical to the Rust edition
 * (parkhub-rust RetentionClass enum).
 */
enum RetentionClass: string
{
    case OperationalPresence = 'operational_presence';
    case BookingHistory = 'booking_history';
    case SecurityAuditLog = 'security_audit_log';
    case HrLabour = 'hr_labour';
    case AnprRaw = 'anpr_raw';
    case EvSession = 'ev_session';
    case BillingFiscal = 'billing_fiscal';

    /** Default TTL in days. May be overridden via Settings unless isLegalHold(). */
    public function defaultTtlDays(): int
    {
        return match ($this) {
            self::OperationalPresence => 30,
            self::BookingHistory => 90,
            self::SecurityAuditLog => 180,
            self::HrLabour => 1095,
            self::AnprRaw => 3,
            self::EvSession => 30,
            self::BillingFiscal => 2922,
        };
    }

    /**
     * Whether this class is subject to a statutory retention minimum.
     * Overrides below statutoryMinDays() are rejected with a 422.
     */
    public function isLegalHold(): bool
    {
        return match ($this) {
            self::BillingFiscal, self::HrLabour => true,
            default => false,
        };
    }

    /**
     * Minimum TTL mandated by law; null for non-legal-hold classes.
     *
     * billing_fiscal: 8 years under HGB §257 (GoBD)
     * hr_labour:      3 years under BGB §195 (standard limitation period)
     */
    public function statutoryMinDays(): ?int
    {
        return match ($this) {
            self::BillingFiscal => 2922,
            self::HrLabour => 1095,
            default => null,
        };
    }
}
