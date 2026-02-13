<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ParkingSlot extends Model
{
    use HasUuids;

    protected $fillable = ['lot_id', 'slot_number', 'status', 'reserved_for_department', 'zone_id'];

    protected $appends = ['number'];

    public function getNumberAttribute(): string
    {
        return $this->slot_number;
    }


    public function lot() { return $this->belongsTo(ParkingLot::class, 'lot_id'); }
    public function zone() { return $this->belongsTo(Zone::class); }
    public function bookings() { return $this->hasMany(Booking::class, 'slot_id'); }

    public function activeBooking()
    {
        return $this->hasOne(Booking::class, 'slot_id')
            ->where('status', 'active')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now());
    }
}
