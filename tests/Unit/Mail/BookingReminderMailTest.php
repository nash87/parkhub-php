<?php

namespace Tests\Unit\Mail;

use App\Mail\BookingReminderMail;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReminderMailTest extends TestCase
{
    use RefreshDatabase;

    private function createBookingAndUser(): array
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $lot = ParkingLot::create(['name' => 'West Lot', 'total_slots' => 20]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'C5', 'status' => 'available']);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'West Lot',
            'slot_number' => 'C5',
            'start_time' => '2026-06-15 09:00:00',
            'end_time' => '2026-06-15 18:00:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        return [$booking, $user];
    }

    public function test_envelope_has_correct_subject(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingReminderMail($booking, $user);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Erinnerung', $envelope->subject);
    }

    public function test_subject_includes_slot_number(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingReminderMail($booking, $user);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('C5', $envelope->subject);
    }

    public function test_content_includes_user_name(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingReminderMail($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString('Test User', $content->htmlString);
    }

    public function test_content_includes_lot_name(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingReminderMail($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString('West Lot', $content->htmlString);
    }

    public function test_content_is_valid_html(): void
    {
        [$booking, $user] = $this->createBookingAndUser();
        $mail = new BookingReminderMail($booking, $user);
        $content = $mail->content();

        $this->assertStringContainsString('<!DOCTYPE html>', $content->htmlString);
    }
}
