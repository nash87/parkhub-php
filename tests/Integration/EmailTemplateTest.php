<?php

namespace Tests\Integration;

use App\Mail\BookingConfirmation;
use App\Mail\BookingReminderMail;
use App\Mail\PasswordResetEmail;
use App\Mail\WaitlistSlotAvailableMail;
use App\Mail\WelcomeEmail;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;

class EmailTemplateTest extends IntegrationTestCase
{
    // ── Welcome email ─────────────────────────────────────────────────────

    public function test_welcome_email_contains_user_data(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Welcome Test User',
            'email' => 'welcome@parkhub.test',
        ]);

        Mail::to($user)->send(new WelcomeEmail($user));

        Mail::assertSent(WelcomeEmail::class, function (WelcomeEmail $mail) use ($user) {
            $mail->assertTo($user->email);

            $html = $mail->render();
            $this->assertStringContainsString('Welcome Test User', $html);
            $this->assertStringContainsString('welcome@parkhub.test', $html);
            $this->assertStringContainsString('ParkHub', $html);

            return true;
        });
    }

    public function test_welcome_email_has_correct_subject(): void
    {
        $user = User::factory()->create(['name' => 'Subject Test']);

        $mail = new WelcomeEmail($user);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Willkommen', $envelope->subject);
    }

    // ── Booking confirmation email ──────────────────────────────────────

    public function test_booking_confirmation_contains_booking_details(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Booking User']);
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
            'vehicle_plate' => 'B-TC 1234',
        ]);

        Mail::to($user)->send(new BookingConfirmation($booking, $user));

        Mail::assertSent(BookingConfirmation::class, function (BookingConfirmation $mail) use ($user, $lot, $slot) {
            $mail->assertTo($user->email);

            $html = $mail->render();
            $this->assertStringContainsString('Booking User', $html);
            $this->assertStringContainsString($slot->slot_number, $html);
            $this->assertStringContainsString('B-TC 1234', $html);
            $this->assertStringContainsString('Buchungsbestätigung', $html);

            return true;
        });
    }

    public function test_booking_confirmation_has_correct_subject(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => 'X99',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(8),
            'booking_type' => 'single',
        ]);

        $mail = new BookingConfirmation($booking, $user);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Buchungsbestätigung', $envelope->subject);
        $this->assertStringContainsString('X99', $envelope->subject);
    }

    // ── Booking reminder email ──────────────────────────────────────────

    public function test_booking_reminder_email_sent_successfully(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Reminder User']);
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addDay()->setHour(8),
            'end_time' => now()->addDay()->setHour(18),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        Mail::to($user)->send(new BookingReminderMail($booking, $user));

        Mail::assertSent(BookingReminderMail::class, function (BookingReminderMail $mail) use ($user) {
            $mail->assertTo($user->email);
            return true;
        });
    }

    // ── Password reset email ─────────────────────────────────────────────

    public function test_password_reset_email_contains_token(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Reset User',
            'email' => 'reset@parkhub.test',
        ]);

        $resetToken = 'test-reset-token-abc123';

        Mail::to($user)->send(new PasswordResetEmail($user, $resetToken));

        Mail::assertSent(PasswordResetEmail::class, function (PasswordResetEmail $mail) use ($user) {
            $mail->assertTo($user->email);

            $html = $mail->render();
            $this->assertStringContainsString('Reset User', $html);
            // The email should contain a reset link or token reference
            $this->assertStringContainsString('test-reset-token-abc123', $html);

            return true;
        });
    }

    // ── Waitlist notification email ────────────────────────────────────────

    public function test_waitlist_notification_email_sent_successfully(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Waitlist User']);
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $entry = WaitlistEntry::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'status' => 'waiting',
            'priority' => 1,
        ]);

        Mail::to($user)->send(new WaitlistSlotAvailableMail($entry, $user));

        Mail::assertSent(WaitlistSlotAvailableMail::class, function (WaitlistSlotAvailableMail $mail) use ($user) {
            $mail->assertTo($user->email);
            return true;
        });
    }

    // ── All emails use HTML format ────────────────────────────────────────

    public function test_all_emails_render_as_html(): void
    {
        $user = User::factory()->create(['name' => 'HTML Test']);
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(8),
            'booking_type' => 'single',
        ]);

        // Welcome email
        $welcome = new WelcomeEmail($user);
        $welcomeHtml = $welcome->render();
        $this->assertStringContainsString('<!DOCTYPE html>', $welcomeHtml);
        $this->assertStringContainsString('</html>', $welcomeHtml);

        // Booking confirmation
        $confirmation = new BookingConfirmation($booking, $user);
        $confirmationHtml = $confirmation->render();
        $this->assertStringContainsString('<!DOCTYPE html>', $confirmationHtml);
        $this->assertStringContainsString('</html>', $confirmationHtml);
    }

    // ── Email contains company branding ────────────────────────────────────

    public function test_emails_contain_parkhub_branding(): void
    {
        $user = User::factory()->create(['name' => 'Brand Test']);

        $mail = new WelcomeEmail($user);
        $html = $mail->render();

        $this->assertStringContainsString('ParkHub', $html);
    }
}
