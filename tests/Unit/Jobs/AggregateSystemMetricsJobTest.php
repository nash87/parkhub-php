<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AggregateSystemMetricsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AggregateSystemMetricsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_collects_and_caches_metrics(): void
    {
        (new AggregateSystemMetricsJob)->handle();

        $metrics = Cache::get('system_metrics');
        $this->assertNotNull($metrics);
        $this->assertArrayHasKey('collected_at', $metrics);
        $this->assertArrayHasKey('total_users', $metrics);
        $this->assertArrayHasKey('bookings_today', $metrics);
        $this->assertArrayHasKey('active_bookings', $metrics);
    }

    public function test_resets_rolling_counters(): void
    {
        Cache::put('system_metrics:cache_hits', 100, now()->addHour());
        Cache::put('system_metrics:cache_misses', 50, now()->addHour());
        Cache::put('system_metrics:slow_query_count', 5, now()->addHour());

        (new AggregateSystemMetricsJob)->handle();

        $this->assertEquals(0, Cache::get('system_metrics:cache_hits'));
        $this->assertEquals(0, Cache::get('system_metrics:cache_misses'));
        $this->assertEquals(0, Cache::get('system_metrics:slow_query_count'));
    }

    public function test_includes_php_memory_info(): void
    {
        (new AggregateSystemMetricsJob)->handle();

        $metrics = Cache::get('system_metrics');
        $this->assertArrayHasKey('php_memory_usage_mb', $metrics);
        $this->assertArrayHasKey('php_memory_peak_mb', $metrics);
        $this->assertGreaterThan(0, $metrics['php_memory_usage_mb']);
    }

    public function test_includes_queue_depth(): void
    {
        (new AggregateSystemMetricsJob)->handle();

        $metrics = Cache::get('system_metrics');
        $this->assertArrayHasKey('queue_depth', $metrics);
        $this->assertArrayHasKey('queue_depth_default', $metrics);
    }
}
