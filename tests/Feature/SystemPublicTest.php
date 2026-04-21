<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPublicTest extends TestCase
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
            ->assertJsonPath('status', 'ok');
    }

    public function test_top_level_health_aliases_exist(): void
    {
        $this->getJson('/api/health/live')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');

        $this->getJson('/api/health/ready')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');

        $this->getJson('/api/health/detailed')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['version', 'build', 'php_version', 'laravel_version']]);
    }

    public function test_system_version(): void
    {
        $this->getJson('/api/v1/system/version')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['version', 'build']]);
    }

    public function test_system_maintenance_default_off(): void
    {
        $response = $this->getJson('/api/v1/system/maintenance');
        $response->assertStatus(200)
            ->assertJsonPath('data.active', false);
    }

    public function test_system_maintenance_when_enabled(): void
    {
        Setting::set('maintenance_mode', 'true');
        Setting::set('maintenance_message', 'Scheduled maintenance');

        $response = $this->getJson('/api/v1/system/maintenance');
        $response->assertStatus(200)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.message', 'Scheduled maintenance');
    }

    public function test_public_occupancy_no_auth(): void
    {
        $lot = ParkingLot::create([
            'name' => 'Public Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        for ($i = 1; $i <= 3; $i++) {
            ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => 'P'.$i,
                'status' => 'available',
            ]);
        }

        $response = $this->getJson('/api/public/occupancy');
        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    public function test_public_display_includes_company_name(): void
    {
        Setting::set('company_name', 'TestCo');

        $response = $this->getJson('/api/public/display');
        $response->assertStatus(200)
            ->assertJsonPath('data.company_name', 'TestCo');
    }

    public function test_public_display_includes_active_announcements(): void
    {
        Announcement::create([
            'title' => 'Active one',
            'message' => 'Hello',
            'active' => true,
        ]);
        Announcement::create([
            'title' => 'Inactive one',
            'message' => 'Hidden',
            'active' => false,
        ]);

        $response = $this->getJson('/api/public/display');
        $response->assertStatus(200);

        $announcements = $response->json('data.announcements');
        $this->assertCount(1, $announcements);
        $this->assertEquals('Active one', $announcements[0]['title']);
    }

    public function test_setup_status_returns_json(): void
    {
        $this->getJson('/api/setup/status')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['setup_completed']]);
    }

    public function test_legal_privacy_endpoint(): void
    {
        $this->getJson('/api/legal/privacy')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'privacy');
    }

    public function test_legal_impressum_endpoint(): void
    {
        $this->getJson('/api/legal/impressum')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'impressum');
    }

    public function test_public_impressum_v1(): void
    {
        Setting::set('impressum_provider_name', 'Test GmbH');

        $response = $this->getJson('/api/v1/legal/impressum');
        $response->assertStatus(200)
            ->assertJsonPath('data.provider_name', 'Test GmbH');
    }

    public function test_branding_logo_returns_svg_when_not_set(): void
    {
        $response = $this->get('/api/v1/branding/logo');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_public_occupancy_with_bookings(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Occ Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'O1',
            'status' => 'available',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson('/api/public/occupancy');
        $response->assertStatus(200);
        $data = collect($response->json('data'))->firstWhere('lot_id', $lot->id);
        $this->assertEquals(1, $data['occupied']);
    }

    public function test_metrics_endpoint_exists(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer test-metrics-token'])
            ->getJson('/api/metrics')
            ->assertStatus(200);
    }
}
