<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Analytics Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Analytics Lot',
            'slot_number' => 'A1',
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours(1),
            'booking_type' => 'single',
            'status' => 'confirmed',
            'total_price' => 12.50,
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Analytics Lot',
            'slot_number' => 'A1',
            'start_time' => now()->subDays(2)->setHour(9),
            'end_time' => now()->subDays(2)->setHour(17),
            'booking_type' => 'single',
            'status' => 'completed',
            'total_price' => 25.00,
        ]);

        return [$admin, $user, $lot, $slot];
    }

    public function test_admin_can_access_analytics_overview(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'daily_bookings',
                    'revenue_by_day',
                    'peak_hours',
                    'top_lots',
                    'user_growth',
                    'avg_duration_hours',
                    'total_users',
                    'total_lots',
                ],
            ]);
    }

    public function test_non_admin_cannot_access_analytics(): void
    {
        [, $user] = $this->seedData();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_analytics(): void
    {
        $this->getJson('/api/v1/admin/analytics/overview')
            ->assertStatus(401);
    }

    public function test_analytics_returns_24_peak_hour_bins(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(24, $data['peak_hours']);
        $this->assertEquals(0, $data['peak_hours'][0]['hour']);
        $this->assertEquals(23, $data['peak_hours'][23]['hour']);
    }

    public function test_analytics_returns_correct_booking_counts(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200);
        $data = $response->json('data');

        // We created 2 bookings
        $totalBookings = collect($data['daily_bookings'])->sum('count');
        $this->assertEquals(2, $totalBookings);
    }

    public function test_analytics_returns_revenue_data(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200);
        $data = $response->json('data');

        $totalRevenue = collect($data['revenue_by_day'])->sum('revenue');
        $this->assertEquals(37.50, $totalRevenue);
    }

    public function test_analytics_returns_top_lots(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data['top_lots']);
        $this->assertEquals('Analytics Lot', $data['top_lots'][0]['name']);
        $this->assertEquals(2, $data['top_lots'][0]['booking_count']);
    }

    public function test_analytics_returns_user_growth(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200);
        $data = $response->json('data');

        // 2 users created (admin + user)
        $this->assertEquals(2, $data['total_users']);
        $totalGrowth = collect($data['user_growth'])->sum('new_users');
        $this->assertEquals(2, $totalGrowth);
    }

    public function test_analytics_disabled_when_module_off(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        config(['modules.analytics' => false]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/overview');

        // Module middleware returns 404 when disabled
        $response->assertStatus(404);
    }

    // ── Occupancy endpoint ────────────────────────────────────────────────────

    public function test_admin_can_access_occupancy(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/occupancy');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['occupancy', 'period_days'],
            ]);
    }

    public function test_occupancy_returns_24_hour_bins(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/occupancy');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(24, $data['occupancy']);
        $this->assertEquals(0, $data['occupancy'][0]['hour']);
        $this->assertEquals(23, $data['occupancy'][23]['hour']);
        $this->assertEquals(7, $data['period_days']);
    }

    public function test_non_admin_cannot_access_occupancy(): void
    {
        [, $user] = $this->seedData();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/occupancy');

        $response->assertStatus(403);
    }

    public function test_occupancy_module_disabled_returns_404(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        config(['modules.admin_analytics' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/occupancy')
            ->assertStatus(404);
    }

    // ── Revenue endpoint ──────────────────────────────────────────────────────

    public function test_admin_can_access_revenue(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/revenue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['revenue', 'period_days', 'total_revenue', 'total_bookings'],
            ]);
    }

    public function test_revenue_totals_are_correct(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/revenue');

        $response->assertStatus(200);
        $data = $response->json('data');
        // seedData creates 2 bookings: 12.50 + 25.00 = 37.50 (both non-cancelled)
        $this->assertEquals(37.50, $data['total_revenue']);
        $this->assertEquals(2, $data['total_bookings']);
        $this->assertEquals(30, $data['period_days']);
    }

    public function test_non_admin_cannot_access_revenue(): void
    {
        [, $user] = $this->seedData();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/revenue')
            ->assertStatus(403);
    }

    // ── Popular lots endpoint ─────────────────────────────────────────────────

    public function test_admin_can_access_popular_lots(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/popular-lots');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['lots'],
            ]);
    }

    public function test_popular_lots_returns_correct_lot(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/popular-lots');

        $response->assertStatus(200);
        $lots = $response->json('data.lots');
        $this->assertNotEmpty($lots);
        $this->assertEquals('Analytics Lot', $lots[0]['name']);
        $this->assertEquals(2, $lots[0]['booking_count']);
    }

    public function test_popular_lots_returns_at_most_10(): void
    {
        [$admin] = $this->seedData();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/popular-lots');

        $response->assertStatus(200);
        $lots = $response->json('data.lots');
        $this->assertLessThanOrEqual(10, count($lots));
    }

    public function test_non_admin_cannot_access_popular_lots(): void
    {
        [, $user] = $this->seedData();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/analytics/popular-lots')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_new_endpoints(): void
    {
        $this->getJson('/api/v1/admin/analytics/occupancy')->assertStatus(401);
        $this->getJson('/api/v1/admin/analytics/revenue')->assertStatus(401);
        $this->getJson('/api/v1/admin/analytics/popular-lots')->assertStatus(401);
    }
}
