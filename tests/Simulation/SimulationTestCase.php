<?php

namespace Tests\Simulation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SimulationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Run a named simulation profile and verify consistency.
     */
    protected function runSimulation(string $profileName): void
    {
        $profile = match ($profileName) {
            'small' => SimulationProfile::small(),
            'campus' => SimulationProfile::campus(),
            'enterprise' => SimulationProfile::enterprise(),
            default => throw new \InvalidArgumentException("Unknown profile: {$profileName}"),
        };

        $startTime = microtime(true);

        // 1. Generate realistic data
        $generator = new DataGenerator($profile);
        $generator->generateAll();

        $this->assertGreaterThanOrEqual($profile->userCount, $generator->getUsers()->count());
        $this->assertCount($profile->lotCount, $generator->getLots());
        $this->assertGreaterThanOrEqual(
            $profile->lotCount * $profile->slotsPerLot,
            $generator->getSlots()->count()
        );

        // 2. Run simulation (inject bookings over 30 days)
        $injector = new ApiInjector($this, $generator, $profile);
        $injector->simulate();

        $stats = $injector->getStats();

        // 3. Verify consistency
        $verifier = new ConsistencyVerifier($profile);
        $verifier->verify();

        $elapsed = round(microtime(true) - $startTime, 2);

        // 4. Generate report
        $reporter = new SimulationReporter(
            $profile,
            $stats,
            $verifier->getChecks(),
            $verifier->getDetails(),
            $elapsed,
        );

        // Write JSON report
        $reportPath = storage_path("app/simulation-report-{$profileName}.json");
        $reporter->writeTo($reportPath);
        $reporter->printSummary();

        // 5. Assert all consistency checks pass
        $failures = $verifier->getFailures();
        $this->assertEmpty(
            $failures,
            "Consistency check failures:\n" . json_encode($verifier->getDetails(), JSON_PRETTY_PRINT)
        );

        // 6. Verify minimum expected counts
        $this->assertGreaterThan(0, $stats['bookings_created'], 'Should have created bookings');
        $this->assertGreaterThan(0, $stats['bookings_cancelled'], 'Should have cancellations');

        // Error rate should be under 5%
        $totalAttempts = $stats['bookings_created'] + $stats['bookings_conflicted'] + $stats['errors'];
        if ($totalAttempts > 0) {
            $errorRate = $stats['errors'] / $totalAttempts;
            $this->assertLessThan(0.05, $errorRate, "Error rate {$errorRate} exceeds 5% threshold");
        }
    }
}
