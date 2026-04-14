<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrometheusMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.metrics_token' => 'test-metrics-token']);
    }

    private function metricsGet(string $uri = '/api/metrics')
    {
        return $this->withHeaders(['Authorization' => 'Bearer test-metrics-token'])
            ->get($uri);
    }

    public function test_metrics_endpoint_returns_prometheus_content_type(): void
    {
        $response = $this->metricsGet();

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('version=0.0.4', $response->headers->get('Content-Type'));
    }

    public function test_metrics_endpoint_includes_required_metric_names(): void
    {
        $response = $this->metricsGet();

        $body = $response->getContent();

        $this->assertStringContainsString('parkhub_users_total', $body);
        $this->assertStringContainsString('parkhub_bookings_total', $body);
        $this->assertStringContainsString('parkhub_lots_total', $body);
        $this->assertStringContainsString('parkhub_slots_total', $body);
        $this->assertStringContainsString('parkhub_slots_available', $body);
        $this->assertStringContainsString('parkhub_bookings_active', $body);
    }

    public function test_metrics_includes_help_and_type_directives(): void
    {
        $response = $this->metricsGet();
        $body = $response->getContent();

        $this->assertStringContainsString('# HELP parkhub_users_total', $body);
        $this->assertStringContainsString('# TYPE parkhub_users_total gauge', $body);
        $this->assertStringContainsString('# HELP parkhub_bookings_total', $body);
        $this->assertStringContainsString('# TYPE parkhub_bookings_total gauge', $body);
    }

    public function test_metrics_reflects_user_count(): void
    {
        User::factory()->count(3)->create();

        $response = $this->metricsGet();
        $body = $response->getContent();

        $this->assertStringContainsString('parkhub_users_total 3', $body);
    }

    public function test_metrics_reflects_booking_status(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'booking_type' => 'einmalig',
            'lot_name' => 'Test',
            'slot_number' => 'A1',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'status' => 'confirmed',
        ]);

        $response = $this->metricsGet();
        $body = $response->getContent();

        $this->assertStringContainsString('parkhub_bookings_total{status="confirmed"} 1', $body);
    }

    public function test_metrics_token_blocks_unauthorized_request(): void
    {
        config(['app.metrics_token' => 'secret-token']);

        $response = $this->get('/api/metrics');
        $response->assertStatus(401);
    }

    public function test_metrics_token_allows_authorized_request(): void
    {
        config(['app.metrics_token' => 'secret-token']);

        $response = $this->withHeader('Authorization', 'Bearer secret-token')
            ->get('/api/metrics');

        $response->assertStatus(200);
    }
}
