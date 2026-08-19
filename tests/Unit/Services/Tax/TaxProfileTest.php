<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tax;

use App\Services\Tax\TaxProfileRegistry;
use Tests\TestCase;

/**
 * Configuration-layer unit tests for {@see TaxProfileRegistry}.
 *
 * Mirrors the Rust-side `api::tax` tests so both backends assert the same
 * jurisdiction rates and reverse-charge semantics.
 */
final class TaxProfileTest extends TestCase
{
    public function test_tax_profile_de_rate_19(): void
    {
        $de = TaxProfileRegistry::resolveProfile('DE');

        $this->assertSame('DE', $de->country);
        $this->assertEqualsWithDelta(0.19, $de->standardRate, 1e-9);
        $this->assertEqualsWithDelta(0.07, $de->reducedRate, 1e-9);
        $this->assertTrue($de->reverseChargeEu);
    }

    public function test_tax_profile_ch_standard_rate(): void
    {
        $ch = TaxProfileRegistry::resolveProfile('CH');

        $this->assertSame('CH', $ch->country);
        // Switzerland's standard rate: 8.1% since 2024-01-01.
        // https://www.estv.admin.ch/en/vat-rates-switzerland
        $this->assertEqualsWithDelta(0.081, $ch->standardRate, 1e-9);
        // Non-EU → no reverse-charge regime.
        $this->assertFalse($ch->reverseChargeEu);
    }

    public function test_reverse_charge_eu_b2b_applies_0_percent(): void
    {
        // German seller, Austrian B2B buyer with valid VAT ID → 0%.
        $resolved = TaxProfileRegistry::resolveRate('DE', 'AT', 'ATU12345678');

        $this->assertTrue($resolved->isReverseCharge());
        $this->assertEqualsWithDelta(0.0, $resolved->asRate(), 1e-9);
    }

    public function test_reverse_charge_same_country_does_not_apply(): void
    {
        // German seller, German buyer with a VAT ID → domestic sale,
        // standard 19% rate applies.
        $resolved = TaxProfileRegistry::resolveRate('DE', 'DE', 'DE123456789');

        $this->assertFalse($resolved->isReverseCharge());
        $this->assertEqualsWithDelta(0.19, $resolved->asRate(), 1e-9);
    }

    public function test_resolve_profile_unknown_falls_back_to_default(): void
    {
        $profile = TaxProfileRegistry::resolveProfile('ZZ');

        $this->assertSame(TaxProfileRegistry::DEFAULT_COUNTRY, $profile->country);
    }

    public function test_resolve_profile_case_insensitive(): void
    {
        $this->assertSame('DE', TaxProfileRegistry::resolveProfile('de')->country);
        $this->assertSame('GB', TaxProfileRegistry::resolveProfile('gB')->country);
    }

    public function test_ten_profiles_shipped(): void
    {
        $this->assertCount(10, TaxProfileRegistry::all());
    }

    public function test_reverse_charge_requires_vat_id(): void
    {
        $this->assertFalse(TaxProfileRegistry::reverseChargeApplies('DE', 'FR', null));
        $this->assertFalse(TaxProfileRegistry::reverseChargeApplies('DE', 'FR', ''));
        $this->assertFalse(TaxProfileRegistry::reverseChargeApplies('DE', 'FR', '   '));
        // VAT ID too short to be plausible.
        $this->assertFalse(TaxProfileRegistry::reverseChargeApplies('DE', 'FR', 'FR1'));
    }

    public function test_reverse_charge_non_eu_buyer(): void
    {
        // Swiss buyer from a German seller → CH is non-EU, so the
        // Directive does not apply; standard DE rate is charged.
        $resolved = TaxProfileRegistry::resolveRate('DE', 'CH', 'CHE123456789');

        $this->assertFalse($resolved->isReverseCharge());
        $this->assertEqualsWithDelta(0.19, $resolved->asRate(), 1e-9);
    }
}
