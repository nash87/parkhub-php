<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CreditTransaction;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The credit ledger must not be mintable.
 *
 * Three independent holes composed into one exploit available to any
 * unprivileged user: cancel refunded unconditionally with no idempotency
 * and no proof a deduction ever happened; the debit was an unguarded
 * `decrement`; and `POST /bookings/quick` created bookings without touching
 * the ledger at all. Quick-book (no debit) followed by cancel (+1 credit)
 * was unbounded net gain.
 */
class CreditLedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private ParkingLot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('credits_enabled', 'true');
        Setting::set('credits_per_booking', '1');
        $this->lot = ParkingLot::create([
            'name' => 'Credit Lot', 'total_slots' => 10, 'available_slots' => 10, 'status' => 'open', 'hourly_rate' => 2.50,
        ]);
    }

    private function slot(string $number = 'C1'): ParkingSlot
    {
        return ParkingSlot::create([
            'lot_id' => $this->lot->id, 'slot_number' => $number, 'status' => 'available',
        ]);
    }

    private function actingUser(int $credits = 10): array
    {
        $user = User::factory()->create(['role' => 'user', 'credits_balance' => $credits]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /** Cancelling the same booking repeatedly must credit at most once. */
    public function test_repeated_cancellation_refunds_only_once(): void
    {
        [$user, $token] = $this->actingUser(10);
        $slot = $this->slot('R1');

        $create = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHours(2)->toDateTimeString(),
            'end_time' => now()->addHours(4)->toDateTimeString(),
        ])->assertStatus(201);

        $bookingId = $create->json('data.id');
        $this->assertSame(9, $user->fresh()->credits_balance, 'creation should debit one credit');

        for ($i = 0; $i < 4; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson("/api/v1/bookings/{$bookingId}");
        }

        $this->assertSame(10, $user->fresh()->credits_balance, 'repeated cancellation minted credits');
        $this->assertSame(
            1,
            CreditTransaction::where('booking_id', $bookingId)->where('type', 'refund')->count(),
            'more than one refund row was written for a single booking',
        );
    }

    /** A booking that never cost a credit must not refund one. */
    public function test_cancelling_a_booking_that_never_paid_does_not_refund(): void
    {
        [$user, $token] = $this->actingUser(5);
        $slot = $this->slot('N1');

        // A booking created directly, with no deduction row — the shape
        // quick-book used to produce.
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $this->lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $this->lot->name,
            'slot_number' => 'N1',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'standard',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/bookings/{$booking->id}");

        $this->assertSame(5, $user->fresh()->credits_balance, 'refunded a credit that was never taken');
    }

    /**
     * A booking the user actually consumed must not be refundable by
     * "cancelling" it afterwards. The unique index cannot catch this — no
     * refund row exists yet — so the terminal-state gate is what closes it.
     */
    public function test_cancelling_a_completed_booking_does_not_refund(): void
    {
        [$user, $token] = $this->actingUser(10);
        $slot = $this->slot('D1');

        $create = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHours(2)->toDateTimeString(),
            'end_time' => now()->addHours(4)->toDateTimeString(),
        ])->assertStatus(201);

        $booking = Booking::find($create->json('data.id'));
        $this->assertSame(9, $user->fresh()->credits_balance);

        // The booking ran its course.
        $booking->update(['status' => Booking::STATUS_COMPLETED]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/bookings/{$booking->id}");

        $this->assertSame(
            9,
            $user->fresh()->credits_balance,
            'a consumed booking was refunded by cancelling it after the fact',
        );
    }

    /** The debit must be conditional; a balance can never go negative. */
    public function test_booking_is_refused_when_the_balance_is_insufficient(): void
    {
        [$user, $token] = $this->actingUser(0);
        $slot = $this->slot('Z1');

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addHours(2)->toDateTimeString(),
            'end_time' => now()->addHours(4)->toDateTimeString(),
        ])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        $this->assertSame(0, $user->fresh()->credits_balance);
        $this->assertGreaterThanOrEqual(0, $user->fresh()->credits_balance);
    }

    // ── quick-book must obey the same rules as the primary creator ──

    public function test_quick_book_debits_credits(): void
    {
        [$user, $token] = $this->actingUser(3);
        $this->slot('Q1');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', ['lot_id' => $this->lot->id])
            ->assertSuccessful();

        $this->assertSame(2, $user->fresh()->credits_balance, 'quick-book did not take a credit');
        $this->assertSame(1, CreditTransaction::where('user_id', $user->id)->where('type', 'deduction')->count());
    }

    public function test_quick_book_is_refused_without_credits(): void
    {
        [$user, $token] = $this->actingUser(0);
        $this->slot('Q2');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', ['lot_id' => $this->lot->id])
            ->assertStatus(422);

        $this->assertSame(0, Booking::where('user_id', $user->id)->count(), 'a booking was created without credits');
    }

    public function test_quick_book_honours_the_active_booking_cap(): void
    {
        config(['parkhub.max_active_bookings' => 1]);
        [$user, $token] = $this->actingUser(10);
        $this->slot('Q3');
        $this->slot('Q4');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', ['lot_id' => $this->lot->id])
            ->assertSuccessful();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', ['lot_id' => $this->lot->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MAX_ACTIVE_BOOKINGS');
    }

    public function test_quick_book_applies_pricing(): void
    {
        [$user, $token] = $this->actingUser(5);
        $this->slot('Q5');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', ['lot_id' => $this->lot->id])
            ->assertSuccessful();

        $booking = Booking::find($response->json('data.id'));
        $this->assertNotNull($booking, 'quick-book returned no booking id');
        $this->assertNotNull($booking->total_price, 'quick-book persisted a booking with no price');
    }

    /**
     * The conflict window was computed from the requested date while the row
     * was persisted with `start_time => now()`, so quick-booking tomorrow
     * created a booking that started immediately and ran until tomorrow
     * night, occupying today as well.
     */
    public function test_quick_booking_a_future_date_does_not_start_now(): void
    {
        [$user, $token] = $this->actingUser(5);
        $this->slot('Q6');
        $tomorrow = now()->addDay()->toDateString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', ['lot_id' => $this->lot->id, 'date' => $tomorrow])
            ->assertSuccessful();

        $booking = Booking::find($response->json('data.id'));
        $this->assertNotNull($booking);
        $this->assertSame(
            $tomorrow,
            $booking->start_time->toDateString(),
            'a quick booking for tomorrow started on a different day',
        );
    }
}
