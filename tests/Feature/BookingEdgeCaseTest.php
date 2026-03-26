<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(int $totalSlots = 10): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Edge Case Lot',
            'total_slots' => $totalSlots,
            'available_slots' => $totalSlots,
            'status' => 'open',
        ]);

        $slots = [];
        for ($i = 1; $i <= $totalSlots; $i++) {
            $slots[] = ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => 'E'.$i,
                'status' => 'available',
            ]);
        }

        return [$user, $lot, $slots];
    }

    public function test_booking_in_the_past_is_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'start_time' => now()->subHours(2)->toISOString(),
                'end_time' => now()->subHour()->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_BOOKING_TIME');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_exceeding_max_duration_is_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        // Set a maximum booking duration of 8 hours
        Setting::set('max_booking_duration_hours', '8');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(25)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DURATION_TOO_LONG');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_below_min_duration_is_rejected(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        Setting::set('min_booking_duration_hours', '1');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHour()->addMinutes(15)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DURATION_TOO_SHORT');
    }

    public function test_cancel_another_users_booking_fails(): void
    {
        [$owner, $lot, $slots] = $this->createUserAndLot();
        $attacker = User::factory()->create(['role' => 'user']);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$attackerToken)
            ->deleteJson('/api/v1/bookings/'.$booking->id)
            ->assertStatus(403);

        // Booking should still be confirmed
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_quick_book_when_lot_is_full_returns_409(): void
    {
        // Create a lot with exactly 1 slot
        [$user, $lot, $slots] = $this->createUserAndLot(1);
        $user2 = User::factory()->create(['role' => 'user']);

        $tomorrow = now()->addDay()->format('Y-m-d');

        // Fill the only slot
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addDay()->startOfDay(),
            'end_time' => now()->addDay()->endOfDay(),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Second user tries to quick-book — should fail
        $token2 = $user2->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/v1/bookings/quick', [
                'lot_id' => $lot->id,
                'date' => $tomorrow,
                'booking_type' => 'full_day',
            ]);

        $response->assertStatus(409);
    }

    public function test_checkin_on_booking(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->subMinutes(10),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/'.$booking->id.'/checkin');

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'active',
        ]);
        $this->assertNotNull($booking->fresh()->checked_in_at);
    }

    public function test_checkin_on_another_users_booking_fails(): void
    {
        [$owner, $lot, $slots] = $this->createUserAndLot();
        $attacker = User::factory()->create(['role' => 'user']);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->subMinutes(10),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $attackerToken = $attacker->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$attackerToken)
            ->postJson('/api/v1/bookings/'.$booking->id.'/checkin')
            ->assertStatus(403);
    }

    public function test_calendar_ics_export(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'lot_name' => 'Edge Case Lot',
            'slot_number' => 'E1',
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/user/calendar.ics');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('Parking: E1 (Edge Case Lot)', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
    }

    public function test_calendar_ics_export_empty_when_no_bookings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/user/calendar.ics');

        $response->assertStatus(200);
        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $body);
    }

    public function test_max_bookings_per_day_enforcement(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot(5);
        $token = $user->createToken('test')->plainTextToken;

        Setting::set('max_bookings_per_day', '1');

        $tomorrow = now()->addDay();

        // First booking succeeds
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[0]->id,
                'start_time' => $tomorrow->copy()->setHour(8)->toISOString(),
                'end_time' => $tomorrow->copy()->setHour(10)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(201);

        // Second booking same day should fail
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slots[1]->id,
                'start_time' => $tomorrow->copy()->setHour(12)->toISOString(),
                'end_time' => $tomorrow->copy()->setHour(14)->toISOString(),
                'booking_type' => 'single',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MAX_BOOKINGS_REACHED');
    }

    public function test_booking_update_extend_end_time(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $newEnd = now()->addHours(5)->toISOString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/bookings/'.$booking->id, [
                'end_time' => $newEnd,
            ]);

        $response->assertStatus(200);
    }

    public function test_booking_show_forbidden_for_other_user(): void
    {
        [$owner, $lot, $slots] = $this->createUserAndLot();
        $other = User::factory()->create(['role' => 'user']);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $otherToken = $other->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson('/api/v1/bookings/'.$booking->id)
            ->assertStatus(403);
    }
}
