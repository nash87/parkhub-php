<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $lot_id
 * @property string $slot_id
 * @property string $booking_type
 * @property ?string $lot_name
 * @property ?string $slot_number
 * @property ?string $vehicle_plate
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property string $status
 * @property ?string $notes
 * @property ?array<string, mixed> $recurrence
 * @property ?Carbon $checked_in_at
 * @property ?string $base_price
 * @property ?string $tax_amount
 * @property ?string $total_price
 * @property string $currency
 * @property ?string $tenant_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read ParkingLot $lot
 * @property-read ParkingLot $parkingLot
 * @property-read ParkingSlot $slot
 * @property-read Collection<int, BookingNote> $bookingNotes
 */
class Booking extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_ACTIVE = 'active';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_COMPLETED = 'completed';

    const STATUS_NO_SHOW = 'no_show';

    const STATUS_RELEASED_NO_SHOW = 'released_no_show';

    protected $fillable = [
        'user_id', 'lot_id', 'slot_id', 'booking_type', 'lot_name', 'slot_number',
        'vehicle_plate', 'start_time', 'end_time', 'status', 'notes',
        'recurrence', 'checked_in_at',
        'base_price', 'tax_amount', 'total_price', 'currency',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'checked_in_at' => 'datetime',
            'recurrence' => 'array',
            'base_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function parkingLot(): BelongsTo
    {
        return $this->lot();
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }

    public function bookingNotes(): HasMany
    {
        return $this->hasMany(BookingNote::class);
    }
}
