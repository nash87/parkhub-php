<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A concrete slot offer created when a no-show booking is released and the
 * waitlist is promoted. Tracks the specific slot and expiry window.
 *
 * @property string $id
 * @property string $waitlist_entry_id
 * @property string $released_booking_id
 * @property string $lot_id
 * @property string $slot_id
 * @property string $user_id
 * @property string $status pending|claimed|expired|declined
 * @property Carbon $expires_at
 * @property ?string $claimed_booking_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WaitlistOffer extends Model
{
    use HasUuids;

    const STATUS_PENDING = 'pending';

    const STATUS_CLAIMED = 'claimed';

    const STATUS_EXPIRED = 'expired';

    const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'waitlist_entry_id',
        'released_booking_id',
        'lot_id',
        'slot_id',
        'user_id',
        'status',
        'expires_at',
        'claimed_booking_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function waitlistEntry(): BelongsTo
    {
        return $this->belongsTo(WaitlistEntry::class);
    }

    public function releasedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'released_booking_id');
    }

    public function claimedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'claimed_booking_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isFuture();
    }
}
