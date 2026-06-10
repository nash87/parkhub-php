<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Retention;

use App\Services\Retention\RetentionClass;
use PHPUnit\Framework\TestCase;

class RetentionClassTest extends TestCase
{
    public function test_all_classes_have_expected_default_ttl_days(): void
    {
        $expected = [
            'operational_presence' => 30,
            'booking_history' => 90,
            'security_audit_log' => 180,
            'hr_labour' => 1095,
            'anpr_raw' => 3,
            'ev_session' => 30,
            'billing_fiscal' => 2922,
        ];

        foreach ($expected as $value => $days) {
            $class = RetentionClass::from($value);
            $this->assertSame($days, $class->defaultTtlDays(), "TTL mismatch for {$value}");
        }
    }

    public function test_billing_fiscal_and_hr_labour_are_legal_hold(): void
    {
        $this->assertTrue(RetentionClass::BillingFiscal->isLegalHold());
        $this->assertTrue(RetentionClass::HrLabour->isLegalHold());
    }

    public function test_other_classes_are_not_legal_hold(): void
    {
        $nonLegal = [
            RetentionClass::OperationalPresence,
            RetentionClass::BookingHistory,
            RetentionClass::SecurityAuditLog,
            RetentionClass::AnprRaw,
            RetentionClass::EvSession,
        ];

        foreach ($nonLegal as $class) {
            $this->assertFalse($class->isLegalHold(), "{$class->value} should not be legal hold");
        }
    }

    public function test_legal_hold_classes_have_statutory_min_days(): void
    {
        $this->assertSame(2922, RetentionClass::BillingFiscal->statutoryMinDays());
        $this->assertSame(1095, RetentionClass::HrLabour->statutoryMinDays());
    }

    public function test_non_legal_hold_classes_have_no_statutory_min_days(): void
    {
        $this->assertNull(RetentionClass::OperationalPresence->statutoryMinDays());
        $this->assertNull(RetentionClass::BookingHistory->statutoryMinDays());
        $this->assertNull(RetentionClass::SecurityAuditLog->statutoryMinDays());
        $this->assertNull(RetentionClass::AnprRaw->statutoryMinDays());
        $this->assertNull(RetentionClass::EvSession->statutoryMinDays());
    }

    public function test_seven_classes_defined(): void
    {
        $this->assertCount(7, RetentionClass::cases());
    }
}
