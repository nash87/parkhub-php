<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $lot_id
 * @property ?string $slot_id
 * @property int $priority
 * @property string $status
 * @property ?Carbon $notified_at
 * @property ?Carbon $offer_expires_at
 * @property ?string $accepted_booking_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?User $user
 * @property-read ?ParkingLot $lot
 * @property-read ?ParkingSlot $slot
 */
class WaitlistEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'lot_id', 'slot_id', 'priority',
        'status', 'notified_at', 'offer_expires_at', 'accepted_booking_id',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'priority' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lot()
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function slot()
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }
}
