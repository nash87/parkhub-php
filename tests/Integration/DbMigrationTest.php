<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DbMigrationTest extends IntegrationTestCase
{
    // ── Core tables exist after migration ──────────────────────────────────

    public function test_users_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumn('users', 'id'));
        $this->assertTrue(Schema::hasColumn('users', 'username'));
        $this->assertTrue(Schema::hasColumn('users', 'email'));
        $this->assertTrue(Schema::hasColumn('users', 'password'));
        $this->assertTrue(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
        $this->assertTrue(Schema::hasColumn('users', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('users', 'two_factor_enabled'));
        $this->assertTrue(Schema::hasColumn('users', 'credits_balance'));
    }

    public function test_parking_lots_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('parking_lots'));
        $this->assertTrue(Schema::hasColumn('parking_lots', 'id'));
        $this->assertTrue(Schema::hasColumn('parking_lots', 'name'));
        $this->assertTrue(Schema::hasColumn('parking_lots', 'total_slots'));
        $this->assertTrue(Schema::hasColumn('parking_lots', 'available_slots'));
        $this->assertTrue(Schema::hasColumn('parking_lots', 'status'));
        $this->assertTrue(Schema::hasColumn('parking_lots', 'tenant_id'));
    }

    public function test_parking_slots_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('parking_slots'));
        $this->assertTrue(Schema::hasColumn('parking_slots', 'id'));
        $this->assertTrue(Schema::hasColumn('parking_slots', 'lot_id'));
        $this->assertTrue(Schema::hasColumn('parking_slots', 'slot_number'));
        $this->assertTrue(Schema::hasColumn('parking_slots', 'status'));
        $this->assertTrue(Schema::hasColumn('parking_slots', 'slot_type'));
    }

    public function test_bookings_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('bookings'));
        $this->assertTrue(Schema::hasColumn('bookings', 'id'));
        $this->assertTrue(Schema::hasColumn('bookings', 'user_id'));
        $this->assertTrue(Schema::hasColumn('bookings', 'lot_id'));
        $this->assertTrue(Schema::hasColumn('bookings', 'slot_id'));
        $this->assertTrue(Schema::hasColumn('bookings', 'start_time'));
        $this->assertTrue(Schema::hasColumn('bookings', 'end_time'));
        $this->assertTrue(Schema::hasColumn('bookings', 'status'));
        $this->assertTrue(Schema::hasColumn('bookings', 'booking_type'));
        $this->assertTrue(Schema::hasColumn('bookings', 'tenant_id'));
    }

    public function test_recurring_bookings_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('recurring_bookings'));
        $this->assertTrue(Schema::hasColumn('recurring_bookings', 'id'));
        $this->assertTrue(Schema::hasColumn('recurring_bookings', 'user_id'));
        $this->assertTrue(Schema::hasColumn('recurring_bookings', 'lot_id'));
        $this->assertTrue(Schema::hasColumn('recurring_bookings', 'days_of_week'));
        $this->assertTrue(Schema::hasColumn('recurring_bookings', 'active'));
    }

    public function test_webhooks_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('webhooks'));
        $this->assertTrue(Schema::hasColumn('webhooks', 'id'));
        $this->assertTrue(Schema::hasColumn('webhooks', 'url'));
        $this->assertTrue(Schema::hasColumn('webhooks', 'events'));
        $this->assertTrue(Schema::hasColumn('webhooks', 'active'));
    }

    public function test_tenants_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasColumn('tenants', 'id'));
        $this->assertTrue(Schema::hasColumn('tenants', 'name'));
        $this->assertTrue(Schema::hasColumn('tenants', 'domain'));
        $this->assertTrue(Schema::hasColumn('tenants', 'branding'));
    }

    public function test_vehicles_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('vehicles'));
        $this->assertTrue(Schema::hasColumn('vehicles', 'id'));
        $this->assertTrue(Schema::hasColumn('vehicles', 'user_id'));
        $this->assertTrue(Schema::hasColumn('vehicles', 'plate'));
    }

    public function test_absences_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('absences'));
        $this->assertTrue(Schema::hasColumn('absences', 'id'));
        $this->assertTrue(Schema::hasColumn('absences', 'user_id'));
        $this->assertTrue(Schema::hasColumn('absences', 'absence_type'));
    }

    public function test_audit_log_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('audit_log'));
        $this->assertTrue(Schema::hasColumn('audit_log', 'id'));
        $this->assertTrue(Schema::hasColumn('audit_log', 'action'));
        $this->assertTrue(Schema::hasColumn('audit_log', 'user_id'));
    }

    public function test_waitlist_entries_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('waitlist_entries'));
        $this->assertTrue(Schema::hasColumn('waitlist_entries', 'id'));
        $this->assertTrue(Schema::hasColumn('waitlist_entries', 'user_id'));
        $this->assertTrue(Schema::hasColumn('waitlist_entries', 'lot_id'));
        $this->assertTrue(Schema::hasColumn('waitlist_entries', 'status'));
    }

    public function test_swap_requests_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('swap_requests'));
        $this->assertTrue(Schema::hasColumn('swap_requests', 'id'));
        $this->assertTrue(Schema::hasColumn('swap_requests', 'requester_id'));
        $this->assertTrue(Schema::hasColumn('swap_requests', 'target_id'));
        $this->assertTrue(Schema::hasColumn('swap_requests', 'status'));
    }

    public function test_credit_transactions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('credit_transactions'));
        $this->assertTrue(Schema::hasColumn('credit_transactions', 'id'));
        $this->assertTrue(Schema::hasColumn('credit_transactions', 'user_id'));
        $this->assertTrue(Schema::hasColumn('credit_transactions', 'amount'));
        $this->assertTrue(Schema::hasColumn('credit_transactions', 'type'));
    }

    // ── Support tables ────────────────────────────────────────────────────

    public function test_personal_access_tokens_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
    }

    public function test_cache_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
    }

    public function test_jobs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
    }

    // ── Database health check ──────────────────────────────────────────────

    public function test_health_endpoint_reports_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);
    }

    public function test_database_connection_works(): void
    {
        // Simple query to verify DB is operational
        $result = DB::select('SELECT 1 as ok');
        $this->assertNotEmpty($result);
        $this->assertEquals(1, $result[0]->ok);
    }

    // ── Seeder creates expected data ──────────────────────────────────────

    public function test_seeder_creates_test_user(): void
    {
        // DatabaseSeeder is run in setUp via $this->seed()
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    // ── Foreign key constraints ────────────────────────────────────────────

    public function test_booking_references_valid_user(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        // Creating a booking with valid user should work
        $booking = \App\Models\Booking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(8),
            'booking_type' => 'single',
        ]);

        $this->assertNotNull($booking->id);
        $this->assertEquals($this->regularUser->id, $booking->user_id);
    }

    public function test_slot_references_valid_lot(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $this->assertEquals($lot->id, $slot->lot_id);
        $this->assertInstanceOf(\App\Models\ParkingLot::class, $slot->lot);
    }

    // ── Soft deletes ───────────────────────────────────────────────────────

    public function test_users_support_soft_delete(): void
    {
        $user = \App\Models\User::factory()->create();
        $userId = $user->id;

        $user->delete();

        // Should be soft-deleted (exists in DB but marked as deleted)
        $this->assertSoftDeleted('users', ['id' => $userId]);

        // Should not appear in normal queries
        $this->assertNull(\App\Models\User::find($userId));

        // Should appear in withTrashed queries
        $this->assertNotNull(\App\Models\User::withTrashed()->find($userId));
    }
}
