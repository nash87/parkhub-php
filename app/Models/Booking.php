<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'lot_id', 'slot_id', 'booking_type', 'lot_name', 'slot_number',
        'vehicle_plate', 'start_time', 'end_time', 'status', 'notes',
        'recurrence', 'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'checked_in_at' => 'datetime',
            'recurrence' => 'array',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function lot() { return $this->belongsTo(ParkingLot::class, 'lot_id'); }
    public function slot() { return $this->belongsTo(ParkingSlot::class, 'slot_id'); }
    public function bookingNotes() { return $this->hasMany(BookingNote::class); }
}
