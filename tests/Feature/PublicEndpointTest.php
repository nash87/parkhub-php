<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\ParkingLot;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.metrics_token' => 'test-metrics-token']);
    }

    public function test_health_check(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'version']);
    }

    public function test_legal_privacy(): void
    {
        $this->getJson('/api/legal/privacy')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'privacy');
    }

    public function test_legal_impressum(): void
    {
        $this->getJson('/api/legal/impressum')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'impressum');
    }

    public function test_public_occupancy(): void
    {
        ParkingLot::create(['name' => 'Public Test', 'total_slots' => 10]);

        $this->getJson('/api/public/occupancy')
            ->assertStatus(200);
    }

    public function test_public_display(): void
    {
        ParkingLot::create(['name' => 'Display Test', 'total_slots' => 5]);

        $this->getJson('/api/public/display')
            ->assertStatus(200);
    }

    public function test_setup_status(): void
    {
        $this->getJson('/api/setup/status')
            ->assertStatus(200);
    }

    public function test_metrics_endpoint_accessible(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer test-metrics-token'])
            ->get('/api/metrics')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    public function test_v1_health_check(): void
    {
        $this->getJson('/api/v1/health')
            ->assertStatus(200);
    }

    public function test_v1_branding(): void
    {
        Setting::set('company_name', 'TestCorp');

        $this->getJson('/api/v1/branding')
            ->assertStatus(200);
    }

    public function test_v1_vapid_key(): void
    {
        config(['services.webpush.vapid_public_key' => 'BTestVapidKey123']);
        $this->getJson('/api/v1/push/vapid-key')
            ->assertStatus(200);
    }

    public function test_v1_active_announcements(): void
    {
        Announcement::create([
            'title' => 'Test',
            'message' => 'Hello',
            'severity' => 'info',
            'active' => true,
        ]);

        $this->getJson('/api/v1/announcements/active')
            ->assertStatus(200);
    }
}
