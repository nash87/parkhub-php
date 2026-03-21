<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithBooking(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Invoice Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'B1',
            'status' => 'available',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Invoice Test Lot',
            'slot_number' => 'B1',
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
            'booking_type' => 'single',
            'status' => 'completed',
            'base_price' => '5.00',
            'total_price' => '5.00',
        ]);

        return [$user, $booking];
    }

    public function test_invoice_requires_authentication(): void
    {
        $this->getJson('/api/v1/bookings/some-id/invoice')
            ->assertStatus(401);
    }

    public function test_user_can_get_invoice_for_own_booking(): void
    {
        [$user, $booking] = $this->createUserWithBooking();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/bookings/'.$booking->id.'/invoice');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function test_invoice_html_contains_booking_id(): void
    {
        [$user, $booking] = $this->createUserWithBooking();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/bookings/'.$booking->id.'/invoice');

        $response->assertStatus(200);
        $this->assertStringContainsString($booking->id, $response->getContent());
    }

    public function test_user_cannot_get_invoice_for_other_users_booking(): void
    {
        [$user, $booking] = $this->createUserWithBooking();
        $otherUser = User::factory()->create(['role' => 'user']);
        $token = $otherUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/bookings/'.$booking->id.'/invoice');

        $response->assertStatus(404);
    }

    public function test_invoice_for_nonexistent_booking_returns_404(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/bookings/00000000-0000-0000-0000-000000000000/invoice');

        $response->assertStatus(404);
    }
}
