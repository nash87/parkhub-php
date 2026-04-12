<?php

namespace Tests\Simulation;

class SimulationReporter
{
    private SimulationProfile $profile;

    private array $stats;

    private array $checks;

    private array $details;

    private float $elapsed;

    public function __construct(
        SimulationProfile $profile,
        array $stats,
        array $checks,
        array $details,
        float $elapsed,
    ) {
        $this->profile = $profile;
        $this->stats = $stats;
        $this->checks = $checks;
        $this->details = $details;
        $this->elapsed = $elapsed;
    }

    /**
     * Generate a JSON report matching the Rust simulation report format.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function toArray(): array
    {
        $allPassed = ! in_array(false, $this->checks, true);

        return [
            'simulation' => [
                'profile' => $this->profile->toArray(),
                'runtime_seconds' => round($this->elapsed, 2),
                'result' => $allPassed ? 'PASS' : 'FAIL',
            ],
            'statistics' => [
                'bookings_created' => $this->stats['bookings_created'] ?? 0,
                'bookings_cancelled' => $this->stats['bookings_cancelled'] ?? 0,
                'bookings_conflicted' => $this->stats['bookings_conflicted'] ?? 0,
                'recurring_created' => $this->stats['recurring_created'] ?? 0,
                'waitlist_created' => $this->stats['waitlist_created'] ?? 0,
                'errors' => $this->stats['errors'] ?? 0,
            ],
            'consistency_checks' => array_map(function (bool $passed, string $key) {
                return [
                    'check' => $key,
                    'passed' => $passed,
                    'detail' => $this->details[$key] ?? '',
                ];
            }, $this->checks, array_keys($this->checks)),
            'meta' => [
                'backend' => 'parkhub-php',
                'framework' => 'Laravel ' . app()->version(),
                'php_version' => PHP_VERSION,
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Write the report to disk.
     */
    public function writeTo(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $this->toJson());
    }

    /**
     * Print a human-readable summary to STDOUT.
     */
    public function printSummary(): void
    {
        $allPassed = ! in_array(false, $this->checks, true);
        $result = $allPassed ? 'PASS' : 'FAIL';

        echo "\n";
        echo "=== Simulation Report: {$this->profile->name} ===\n";
        echo "Result: {$result}\n";
        echo "Runtime: {$this->elapsed}s\n";
        echo "\n";
        echo "Statistics:\n";
        echo "  Bookings created:   {$this->stats['bookings_created']}\n";
        echo "  Bookings cancelled: {$this->stats['bookings_cancelled']}\n";
        echo "  Conflicts detected: {$this->stats['bookings_conflicted']}\n";
        echo "  Recurring created:  {$this->stats['recurring_created']}\n";
        echo "  Waitlist entries:   {$this->stats['waitlist_created']}\n";
        echo "  Errors:             {$this->stats['errors']}\n";
        echo "\n";
        echo "Consistency Checks:\n";

        foreach ($this->checks as $name => $passed) {
            $icon = $passed ? 'OK' : 'FAIL';
            $detail = $this->details[$name] ?? '';
            echo "  [{$icon}] {$name}: {$detail}\n";
        }

        echo "\n";
    }
}
