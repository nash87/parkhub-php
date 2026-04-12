<?php

namespace Tests\Integration;

use App\Models\RecurringBooking;
use App\Models\User;

class RecurringBookingTest extends IntegrationTestCase
{
    // ── Create 30-day daily recurring ─────────────────────────────────────

    public function test_create_30_day_daily_recurring_booking(): void
    {
        $lot = $this->createLotWithSlots(5);
        $slot = $lot->slots()->first();

        $startDate = now()->addDay()->format('Y-m-d');
        $endDate = now()->addDays(31)->format('Y-m-d');

        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1, 2, 3, 4, 5],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('recurring_bookings', [
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'active' => true,
        ]);
    }

    // ── List recurring bookings ───────────────────────────────────────────

    public function test_list_recurring_bookings(): void
    {
        $lot = $this->createLotWithSlots(3);
        $slots = $lot->slots;

        RecurringBooking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'days_of_week' => [1, 3, 5],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'active' => true,
        ]);

        RecurringBooking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[1]->id,
            'days_of_week' => [2, 4],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonths(2)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'active' => true,
        ]);

        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/recurring-bookings');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    // ── Delete recurring booking ──────────────────────────────────────────

    public function test_delete_recurring_booking(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $recurring = RecurringBooking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [1, 2, 3],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'active' => true,
        ]);

        $response = $this->withHeaders($this->userHeaders())
            ->deleteJson("/api/v1/recurring-bookings/{$recurring->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('recurring_bookings', ['id' => $recurring->id]);
    }

    // ── Update recurring booking ──────────────────────────────────────────

    public function test_update_recurring_booking(): void
    {
        $lot = $this->createLotWithSlots(2);
        $slots = $lot->slots;

        $recurring = RecurringBooking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'days_of_week' => [1, 3],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'active' => true,
        ]);

        $response = $this->withHeaders($this->userHeaders())
            ->putJson("/api/v1/recurring-bookings/{$recurring->id}", [
                'lot_id' => $lot->id,
                'slot_id' => $slots[1]->id,
                'days_of_week' => [1, 2, 3, 4, 5],
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonths(3)->format('Y-m-d'),
                'start_time' => '07:00',
                'end_time' => '18:00',
            ]);

        $response->assertStatus(200);
    }

    // ── Cannot delete another user's recurring booking ─────────────────────

    public function test_cannot_delete_another_users_recurring_booking(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $owner = User::factory()->create(['role' => 'user']);
        $recurring = RecurringBooking::create([
            'user_id' => $owner->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'days_of_week' => [1],
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'active' => true,
        ]);

        $this->withHeaders($this->userHeaders())
            ->deleteJson("/api/v1/recurring-bookings/{$recurring->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('recurring_bookings', ['id' => $recurring->id]);
    }

    // ── Validation: start_date in the past ────────────────────────────────

    public function test_start_date_in_past_rejected(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1, 3],
                'start_date' => now()->subWeek()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(422);
    }

    // ── Validation: end_date before start_date ────────────────────────────

    public function test_end_date_before_start_date_rejected(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'days_of_week' => [1, 3],
                'start_date' => now()->addMonth()->format('Y-m-d'),
                'end_date' => now()->addDay()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(422);
    }

    // ── Validation: days_of_week required ──────────────────────────────────

    public function test_days_of_week_required(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/recurring-bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addMonth()->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(422);
    }

    // ── Requires authentication ────────────────────────────────────────────

    public function test_recurring_bookings_require_auth(): void
    {
        $this->getJson('/api/v1/recurring-bookings')
            ->assertStatus(401);

        $this->postJson('/api/v1/recurring-bookings', [])
            ->assertStatus(401);
    }

    // ── Multiple recurring bookings coexist ────────────────────────────────

    public function test_multiple_recurring_bookings_for_different_slots(): void
    {
        $lot = $this->createLotWithSlots(5);
        $slots = $lot->slots;

        // Create 3 recurring bookings on different slots
        for ($i = 0; $i < 3; $i++) {
            $response = $this->withHeaders($this->userHeaders())
                ->postJson('/api/v1/recurring-bookings', [
                    'lot_id' => $lot->id,
                    'slot_id' => $slots[$i]->id,
                    'days_of_week' => [$i + 1],
                    'start_date' => now()->addDay()->format('Y-m-d'),
                    'end_date' => now()->addMonth()->format('Y-m-d'),
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                ]);

            $response->assertStatus(201);
        }

        // List should show all 3
        $listResponse = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/recurring-bookings');

        $listResponse->assertStatus(200);
        $this->assertCount(3, $listResponse->json('data'));
    }
}
