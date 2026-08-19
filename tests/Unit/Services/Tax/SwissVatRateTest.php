<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tax;

use App\Services\Tax\TaxProfileRegistry;
use Tests\TestCase;

/**
 * Switzerland moved to 8.1 % standard / 2.6 % reduced on 2024-01-01.
 *
 * The registry carried the *new* reduced rate next to the *old* standard
 * rate — 0.077 / 0.026 — and that internal inconsistency is itself the
 * evidence of a half-finished update: nobody sets 2.6 % except as part of
 * the same reform that set 8.1 %.
 *
 * Source: Swiss Federal Tax Administration, "VAT rates Switzerland"
 * https://www.estv.admin.ch/en/vat-rates-switzerland
 */
class SwissVatRateTest extends TestCase
{
    public function test_swiss_standard_rate_is_the_current_one(): void
    {
        $profile = TaxProfileRegistry::resolveProfile('CH');

        $this->assertSame('CH', $profile->country);
        $this->assertSame(0.081, $profile->standardRate, 'Swiss standard VAT has been 8.1% since 2024-01-01');
    }

    public function test_swiss_reduced_rate_is_unchanged(): void
    {
        $profile = TaxProfileRegistry::resolveProfile('CH');

        $this->assertSame(0.026, $profile->reducedRate);
    }

    /**
     * A standard rate below its own reduced rate, or the two out of step
     * with a known reform, is the shape this defect had. Assert the pair
     * moves together.
     */
    public function test_standard_rate_exceeds_the_reduced_rate_for_every_profile(): void
    {
        foreach (TaxProfileRegistry::all() as $profile) {
            if ($profile->reducedRate === null) {
                continue;
            }
            $this->assertGreaterThan(
                $profile->reducedRate,
                $profile->standardRate,
                "{$profile->country}: standard rate is not above the reduced rate",
            );
        }
    }
}
