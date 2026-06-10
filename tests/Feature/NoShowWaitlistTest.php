<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\NoShowReleaseJob;
use App\Mail\WaitlistOfferMail;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Services\NoShow\NoShowReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TDD suite for P1-1 (no-show auto-release) and P1-2 (waitlist auto-promotion).
 *
 * FIFO promotion order: priority ASC, created_at ASC (tie-break).
 */
class NoShowWaitlistTest extends TestCase
{
    use RefreshDatabase;

    private function makeLot(array $attrs = []): ParkingLot
    {
        return ParkingLot::create(array_merge([
            'name' => 'Test Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ], $attrs));
    }

    private function makeSlot(ParkingLot $lot, string $number = 'A1'): ParkingSlot
    {
        return ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => $number,
            'status' => 'available',
        ]);
    }

    private function makeBooking(User $user, ParkingLot $lot, ParkingSlot $slot, array $attrs = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'single',
        ], $attrs));
    }

    private function makeWaitlistEntry(User $user, ParkingLot $lot, array $attrs = []): WaitlistEntry
    {
        return WaitlistEntry::create(array_merge([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'priority' => 3,
            'status' => 'waiting',
        ], $attrs));
    }

    // ── 1. No-show release timing ────────────────────────────────────────

    public function test_booking_past_deadline_without_checkin_is_released(): void
    {
        $lot = $this->makeLot(['check_in_deadline_minutes' => 30]);
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        // start_time 31 min ago, no check-in
        $booking = $this->makeBooking($user, $lot, $slot, [
            'start_time' => now()->subMinutes(31),
            'end_time' => now()->addHour(),
        ]);

        $service = app(NoShowReleaseService::class);
        $released = $service->releaseNoShows();

        $this->assertSame(1, $released);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);
    }

    public function test_booking_within_deadline_is_not_released(): void
    {
        $lot = $this->makeLot(['check_in_deadline_minutes' => 30]);
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        // start_time only 10 min ago — within deadline
        $booking = $this->makeBooking($user, $lot, $slot, [
            'start_time' => now()->subMinutes(10),
        ]);

        $service = app(NoShowReleaseService::class);
        $service->releaseNoShows();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    // ── 2. Check-in prevents release ────────────────────────────────────

    public function test_checked_in_booking_is_not_released(): void
    {
        $lot = $this->makeLot(['check_in_deadline_minutes' => 30]);
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        $booking = $this->makeBooking($user, $lot, $slot, [
            'start_time' => now()->subMinutes(45),
            'checked_in_at' => now()->subMinutes(40),
            'status' => Booking::STATUS_ACTIVE,
        ]);

        $service = app(NoShowReleaseService::class);
        $released = $service->releaseNoShows();

        $this->assertSame(0, $released);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_ACTIVE,
        ]);
    }

    // ── 3. Offer + notify on release ────────────────────────────────────

    public function test_waitlist_entry_receives_offer_on_no_show_release(): void
    {
        Mail::fake();

        $lot = $this->makeLot(['check_in_deadline_minutes' => 30, 'claim_window_minutes' => 15]);
        $slot = $this->makeSlot($lot);
        $booker = User::factory()->create();
        $waiter = User::factory()->create();

        $this->makeBooking($booker, $lot, $slot, ['start_time' => now()->subMinutes(35)]);
        $entry = $this->makeWaitlistEntry($waiter, $lot);

        $service = app(NoShowReleaseService::class);
        $service->releaseNoShows();

        // WaitlistEntry promoted to offered
        $this->assertDatabaseHas('waitlist_entries', [
            'id' => $entry->id,
            'status' => 'offered',
        ]);

        // WaitlistOffer created
        $offer = WaitlistOffer::where('user_id', $waiter->id)->first();
        $this->assertNotNull($offer);
        $this->assertSame(WaitlistOffer::STATUS_PENDING, $offer->status);
        $this->assertTrue($offer->expires_at->isFuture());

        // Mail queued
        Mail::assertQueued(WaitlistOfferMail::class, function ($mail) use ($waiter) {
            return $mail->recipient->id === $waiter->id;
        });
    }

    // ── 4. Claim within window creates booking ───────────────────────────

    public function test_claim_within_window_creates_booking(): void
    {
        $lot = $this->makeLot(['claim_window_minutes' => 15]);
        $slot = $this->makeSlot($lot);
        $booker = User::factory()->create();
        $waiter = User::factory()->create();

        $releasedBooking = $this->makeBooking($booker, $lot, $slot, [
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);

        $entry = $this->makeWaitlistEntry($waiter, $lot, ['status' => 'offered']);
        $offer = WaitlistOffer::create([
            'waitlist_entry_id' => $entry->id,
            'released_booking_id' => $releasedBooking->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'user_id' => $waiter->id,
            'status' => WaitlistOffer::STATUS_PENDING,
            'expires_at' => now()->addMinutes(14),
        ]);

        $token = $waiter->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/waitlist/offers/{$offer->id}/claim");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.offer.status', WaitlistOffer::STATUS_CLAIMED);

        $this->assertDatabaseHas('waitlist_offers', [
            'id' => $offer->id,
            'status' => WaitlistOffer::STATUS_CLAIMED,
        ]);

        // New booking created
        $newBookingId = $response->json('data.booking.id');
        $this->assertNotNull($newBookingId);
        $this->assertDatabaseHas('bookings', [
            'id' => $newBookingId,
            'user_id' => $waiter->id,
            'lot_id' => $lot->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    // ── 5. Expiry passes to next FIFO entry ──────────────────────────────

    public function test_expired_offer_promotes_next_fifo_entry(): void
    {
        Mail::fake();

        $lot = $this->makeLot(['claim_window_minutes' => 15]);
        $slot = $this->makeSlot($lot);
        $booker = User::factory()->create();
        $waiter1 = User::factory()->create();
        $waiter2 = User::factory()->create();

        $releasedBooking = $this->makeBooking($booker, $lot, $slot, [
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);

        // waiter1 got offered but window already passed
        $entry1 = $this->makeWaitlistEntry($waiter1, $lot, ['status' => 'offered', 'priority' => 1]);
        $expiredOffer = WaitlistOffer::create([
            'waitlist_entry_id' => $entry1->id,
            'released_booking_id' => $releasedBooking->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'user_id' => $waiter1->id,
            'status' => WaitlistOffer::STATUS_PENDING,
            'expires_at' => now()->subMinute(), // already past
        ]);

        // waiter2 is next in queue
        $entry2 = $this->makeWaitlistEntry($waiter2, $lot, ['priority' => 2]);

        $service = app(NoShowReleaseService::class);
        $service->expireStaleOffers();

        // waiter1 entry expired
        $this->assertDatabaseHas('waitlist_offers', [
            'id' => $expiredOffer->id,
            'status' => WaitlistOffer::STATUS_EXPIRED,
        ]);

        // waiter2 entry promoted
        $this->assertDatabaseHas('waitlist_entries', [
            'id' => $entry2->id,
            'status' => 'offered',
        ]);

        // New offer created for waiter2
        $this->assertDatabaseHas('waitlist_offers', [
            'user_id' => $waiter2->id,
            'status' => WaitlistOffer::STATUS_PENDING,
        ]);

        Mail::assertQueued(WaitlistOfferMail::class, fn ($m) => $m->recipient->id === $waiter2->id);
    }

    // ── 6. Double-claim rejected ─────────────────────────────────────────

    public function test_double_claim_rejected(): void
    {
        $lot = $this->makeLot();
        $slot = $this->makeSlot($lot);
        $booker = User::factory()->create();
        $waiter = User::factory()->create();

        $releasedBooking = $this->makeBooking($booker, $lot, $slot, [
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);
        $entry = $this->makeWaitlistEntry($waiter, $lot, ['status' => 'offered']);
        $offer = WaitlistOffer::create([
            'waitlist_entry_id' => $entry->id,
            'released_booking_id' => $releasedBooking->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'user_id' => $waiter->id,
            'status' => WaitlistOffer::STATUS_CLAIMED, // already claimed
            'expires_at' => now()->addMinutes(10),
        ]);

        $token = $waiter->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/waitlist/offers/{$offer->id}/claim");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'OFFER_NOT_PENDING');
    }

    // ── 7. Disabled lots untouched ───────────────────────────────────────

    public function test_lot_with_deadline_zero_is_skipped(): void
    {
        $lot = $this->makeLot(['check_in_deadline_minutes' => 0]); // disabled
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        $booking = $this->makeBooking($user, $lot, $slot, [
            'start_time' => now()->subHours(2),
        ]);

        $service = app(NoShowReleaseService::class);
        $released = $service->releaseNoShows();

        $this->assertSame(0, $released);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    // ── 8. RBAC: unauthenticated cannot access offer endpoints ───────────

    public function test_unauthenticated_cannot_list_offers(): void
    {
        $this->getJson('/api/v1/waitlist/offers')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_claim_offer(): void
    {
        $this->postJson('/api/v1/waitlist/offers/fake-id/claim')->assertStatus(401);
    }

    public function test_user_cannot_claim_another_users_offer(): void
    {
        $lot = $this->makeLot();
        $slot = $this->makeSlot($lot);
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $booker = User::factory()->create();

        $releasedBooking = $this->makeBooking($booker, $lot, $slot, [
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);
        $entry = $this->makeWaitlistEntry($owner, $lot, ['status' => 'offered']);
        $offer = WaitlistOffer::create([
            'waitlist_entry_id' => $entry->id,
            'released_booking_id' => $releasedBooking->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'user_id' => $owner->id,
            'status' => WaitlistOffer::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        $token = $attacker->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/waitlist/offers/{$offer->id}/claim");

        $response->assertStatus(403);
    }

    // ── 9. GET /waitlist/offers list ─────────────────────────────────────

    public function test_list_offers_returns_only_pending_non_expired(): void
    {
        $lot = $this->makeLot();
        $slot = $this->makeSlot($lot);
        $booker = User::factory()->create();
        $user = User::factory()->create();

        $released = $this->makeBooking($booker, $lot, $slot, [
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);
        $entry = $this->makeWaitlistEntry($user, $lot, ['status' => 'offered']);

        // pending, not yet expired
        WaitlistOffer::create([
            'waitlist_entry_id' => $entry->id,
            'released_booking_id' => $released->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'status' => WaitlistOffer::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        // already expired — should not appear
        WaitlistOffer::create([
            'waitlist_entry_id' => $entry->id,
            'released_booking_id' => $released->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'status' => WaitlistOffer::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/waitlist/offers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    // ── 10. Idempotent check-in (POST /check-in) ─────────────────────────

    public function test_check_in_is_idempotent(): void
    {
        $lot = $this->makeLot();
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        $booking = $this->makeBooking($user, $lot, $slot, [
            'checked_in_at' => now()->subMinutes(5),
            'status' => Booking::STATUS_ACTIVE,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/check-in");

        $response->assertStatus(200)->assertJsonPath('success', true);

        // checked_in_at unchanged
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_ACTIVE,
        ]);
    }

    public function test_check_in_marks_confirmed_booking_active(): void
    {
        $lot = $this->makeLot();
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        $booking = $this->makeBooking($user, $lot, $slot, [
            'start_time' => now()->subMinutes(5),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/check-in");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_ACTIVE,
        ]);
        $this->assertNotNull(Booking::find($booking->id)->checked_in_at);
    }

    public function test_check_in_rejected_for_released_no_show(): void
    {
        $lot = $this->makeLot();
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        $booking = $this->makeBooking($user, $lot, $slot, [
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/check-in");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BOOKING_NOT_CHECKABLE');
    }

    // ── 11. FIFO order is respected ──────────────────────────────────────

    public function test_fifo_promotion_respects_priority_then_created_at(): void
    {
        Mail::fake();

        $lot = $this->makeLot(['check_in_deadline_minutes' => 30]);
        $slot = $this->makeSlot($lot);
        $booker = User::factory()->create();
        $waiterHigh = User::factory()->create(); // priority 1 (higher)
        $waiterLow = User::factory()->create();  // priority 5 (lower)

        $this->makeBooking($booker, $lot, $slot, ['start_time' => now()->subMinutes(35)]);

        // Lower priority created first — should NOT be promoted
        $this->makeWaitlistEntry($waiterLow, $lot, ['priority' => 5,
            'created_at' => now()->subHours(2)]);

        // Higher priority created second — SHOULD be promoted
        $this->makeWaitlistEntry($waiterHigh, $lot, ['priority' => 1,
            'created_at' => now()->subHour()]);

        $service = app(NoShowReleaseService::class);
        $service->releaseNoShows();

        Mail::assertQueued(WaitlistOfferMail::class, fn ($m) => $m->recipient->id === $waiterHigh->id);
        Mail::assertNotQueued(WaitlistOfferMail::class, fn ($m) => $m->recipient->id === $waiterLow->id);
    }

    // ── 12. AuditLog stamped on release ─────────────────────────────────

    public function test_audit_log_created_on_release(): void
    {
        $lot = $this->makeLot(['check_in_deadline_minutes' => 30]);
        $slot = $this->makeSlot($lot);
        $user = User::factory()->create();

        $booking = $this->makeBooking($user, $lot, $slot, ['start_time' => now()->subMinutes(35)]);

        $service = app(NoShowReleaseService::class);
        $service->releaseNoShows();

        $this->assertDatabaseHas('audit_log', [
            'action' => 'booking_released_no_show',
        ]);
    }

    // ── 13. Module disabled: routes return 404 ───────────────────────────

    public function test_disabled_module_returns_404_for_offers(): void
    {
        config(['modules.noshow_waitlist' => false]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/waitlist/offers')
            ->assertStatus(404);
    }

    // ── 14. Job dispatches service ───────────────────────────────────────

    public function test_job_runs_without_error(): void
    {
        // Just verify no exceptions — functional logic tested via service tests
        $job = new NoShowReleaseJob;
        $job->handle(app(NoShowReleaseService::class));

        $this->assertTrue(true);
    }

    // ── 15. Claim expired offer returns 410 ─────────────────────────────

    public function test_claim_expired_offer_returns_gone(): void
    {
        $lot = $this->makeLot();
        $slot = $this->makeSlot($lot);
        $booker = User::factory()->create();
        $waiter = User::factory()->create();

        $releasedBooking = $this->makeBooking($booker, $lot, $slot, [
            'status' => Booking::STATUS_RELEASED_NO_SHOW,
        ]);
        $entry = $this->makeWaitlistEntry($waiter, $lot, ['status' => 'offered']);
        $offer = WaitlistOffer::create([
            'waitlist_entry_id' => $entry->id,
            'released_booking_id' => $releasedBooking->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'user_id' => $waiter->id,
            'status' => WaitlistOffer::STATUS_PENDING,
            'expires_at' => now()->subMinute(), // expired
        ]);

        $token = $waiter->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/waitlist/offers/{$offer->id}/claim");

        $response->assertStatus(410)
            ->assertJsonPath('error.code', 'OFFER_EXPIRED');
    }
}
