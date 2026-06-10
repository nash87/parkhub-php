<?php

declare(strict_types=1);

namespace App\Services\NoShow;

use App\Mail\WaitlistOfferMail;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * No-show auto-release + waitlist FIFO promotion.
 *
 * Promotion order (FIFO):
 *   1. priority ASC (lower number = higher priority, matching the existing
 *      waitlist ordering convention in WaitlistController::lotWaitlist)
 *   2. created_at ASC (tie-break: first-in wins)
 *
 * Transaction safety: each booking release + offer creation runs in its own
 * DB transaction so a partial failure on one booking does not prevent others
 * from being processed.
 */
final class NoShowReleaseService
{
    private const DEFAULT_DEADLINE_MINUTES = 30;

    private const DEFAULT_CLAIM_WINDOW_MINUTES = 15;

    /**
     * Scan all lots for past-deadline un-checked-in bookings and release them.
     *
     * @return int number of bookings released
     */
    public function releaseNoShows(): int
    {
        $released = 0;

        $lots = ParkingLot::all(['id', 'check_in_deadline_minutes', 'claim_window_minutes']);

        foreach ($lots as $lot) {
            $deadlineMinutes = $lot->check_in_deadline_minutes ?? self::DEFAULT_DEADLINE_MINUTES;

            if ($deadlineMinutes === 0) {
                continue; // feature disabled for this lot
            }

            $cutoff = now()->subMinutes($deadlineMinutes);

            $staleBookings = Booking::where('lot_id', $lot->id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<=', $cutoff)
                ->whereNull('checked_in_at')
                ->get();

            foreach ($staleBookings as $booking) {
                try {
                    $this->releaseBooking($booking, $lot);
                    $released++;
                } catch (\Throwable $e) {
                    Log::error('noshow_release: failed to release booking', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $released;
    }

    /**
     * Release a single booking as no-show and promote the next waitlist entry.
     */
    private function releaseBooking(Booking $booking, ParkingLot $lot): void
    {
        DB::transaction(function () use ($booking, $lot) {
            // Re-read inside transaction to guard against concurrent release
            $fresh = Booking::lockForUpdate()->find($booking->id);
            if ($fresh === null) {
                return;
            }
            if (! in_array($fresh->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE], true)) {
                return; // already released by another process
            }
            if ($fresh->checked_in_at !== null) {
                return; // user checked in just before we ran
            }

            $fresh->update(['status' => Booking::STATUS_RELEASED_NO_SHOW]);

            AuditLog::log([
                'action' => 'booking_released_no_show',
                'details' => [
                    'booking_id' => $fresh->id,
                    'lot_id' => $lot->id,
                    'slot_id' => $fresh->slot_id,
                    'retention_deletion_class' => 'booking_history',
                ],
            ]);

            Log::info('noshow_release: released booking', ['booking_id' => $fresh->id]);

            $this->promoteNextWaitlistEntry($fresh, $lot);
        });
    }

    /**
     * Offer the freed slot to the next FIFO waitlist entry.
     * FIFO order: priority ASC, then created_at ASC.
     */
    private function promoteNextWaitlistEntry(Booking $booking, ParkingLot $lot): void
    {
        $next = WaitlistEntry::where('lot_id', $lot->id)
            ->where('status', 'waiting')
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();

        if ($next === null) {
            return;
        }

        $claimWindowMinutes = $lot->claim_window_minutes ?? self::DEFAULT_CLAIM_WINDOW_MINUTES;
        $expiresAt = now()->addMinutes($claimWindowMinutes);

        $next->update([
            'status' => 'offered',
            'notified_at' => now(),
            'offer_expires_at' => $expiresAt,
        ]);

        $offer = WaitlistOffer::create([
            'waitlist_entry_id' => $next->id,
            'released_booking_id' => $booking->id,
            'lot_id' => $lot->id,
            'slot_id' => $booking->slot_id,
            'user_id' => $next->user_id,
            'status' => WaitlistOffer::STATUS_PENDING,
            'expires_at' => $expiresAt,
        ]);

        $user = $next->user;
        if ($user !== null) {
            Mail::to($user->email)->queue(new WaitlistOfferMail($user, $lot, $offer));
        }

        Log::info('noshow_release: promoted waitlist entry', [
            'entry_id' => $next->id,
            'offer_id' => $offer->id,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Expire pending offers whose window has closed and pass to the next entry.
     * Called by the same NoShowReleaseJob to keep the queue moving.
     */
    public function expireStaleOffers(): void
    {
        $expired = WaitlistOffer::where('status', WaitlistOffer::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->with(['lot', 'waitlistEntry'])
            ->get();

        foreach ($expired as $offer) {
            try {
                DB::transaction(function () use ($offer) {
                    $fresh = WaitlistOffer::lockForUpdate()->find($offer->id);
                    if ($fresh === null || $fresh->status !== WaitlistOffer::STATUS_PENDING) {
                        return;
                    }
                    $fresh->update(['status' => WaitlistOffer::STATUS_EXPIRED]);

                    if ($fresh->waitlistEntry !== null) {
                        $fresh->waitlistEntry->update(['status' => 'expired']);
                    }

                    Log::info('noshow_release: offer expired', ['offer_id' => $fresh->id]);

                    // Promote next entry if a lot is available
                    $lot = ParkingLot::find($fresh->lot_id);
                    if ($lot !== null) {
                        // Retrieve the released booking to re-use its slot
                        $releasedBooking = Booking::find($fresh->released_booking_id);
                        if ($releasedBooking !== null) {
                            $this->promoteNextWaitlistEntry($releasedBooking, $lot);
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::error('noshow_release: failed to expire offer', [
                    'offer_id' => $offer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Claim an offer: create a booking for the freed slot.
     * Transaction-safe — rejects double-claims atomically.
     *
     * @throws \RuntimeException on validation failures (caller wraps in HTTP response)
     */
    public function claimOffer(WaitlistOffer $offer, string $userId): Booking
    {
        return DB::transaction(function () use ($offer, $userId) {
            $fresh = WaitlistOffer::lockForUpdate()->findOrFail($offer->id);

            if ($fresh->user_id !== $userId) {
                throw new \RuntimeException('FORBIDDEN');
            }

            if ($fresh->status !== WaitlistOffer::STATUS_PENDING) {
                throw new \RuntimeException('OFFER_NOT_PENDING');
            }

            if ($fresh->expires_at->isPast()) {
                $fresh->update(['status' => WaitlistOffer::STATUS_EXPIRED]);
                throw new \RuntimeException('OFFER_EXPIRED');
            }

            // Lock the slot to prevent double-booking
            $slot = ParkingSlot::lockForUpdate()->findOrFail($fresh->slot_id);

            // Verify the slot is actually free (no active booking after our release)
            $conflict = Booking::where('slot_id', $slot->id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new \RuntimeException('SLOT_NO_LONGER_AVAILABLE');
            }

            // Find the released booking to inherit time window
            $released = Booking::find($fresh->released_booking_id);
            $startTime = now();
            $endTime = $released ? $released->end_time : now()->addHours(8);

            $booking = Booking::create([
                'user_id' => $userId,
                'lot_id' => $fresh->lot_id,
                'slot_id' => $slot->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => Booking::STATUS_CONFIRMED,
                'booking_type' => 'single',
            ]);

            $fresh->update([
                'status' => WaitlistOffer::STATUS_CLAIMED,
                'claimed_booking_id' => $booking->id,
            ]);

            if ($fresh->waitlistEntry !== null) {
                WaitlistEntry::find($fresh->waitlist_entry_id)?->update([
                    'status' => 'accepted',
                    'accepted_booking_id' => $booking->id,
                ]);
            }

            AuditLog::log([
                'user_id' => $userId,
                'action' => 'waitlist_offer_claimed',
                'details' => [
                    'offer_id' => $fresh->id,
                    'booking_id' => $booking->id,
                    'lot_id' => $fresh->lot_id,
                    'slot_id' => $slot->id,
                    'retention_deletion_class' => 'booking_history',
                ],
            ]);

            return $booking;
        });
    }
}
