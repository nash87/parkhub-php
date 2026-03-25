<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingSlot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['lot_id', 'slot_number', 'status', 'slot_type', 'features', 'reserved_for_department', 'zone_id', 'is_accessible'];

    protected $appends = ['number'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_accessible' => 'boolean',
        ];
    }

    public function getNumberAttribute(): string
    {
        return $this->slot_number;
    }

    public function lot()
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }

    public function activeBooking()
    {
        return $this->hasOne(Booking::class, 'slot_id')
            ->where('status', 'active')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now());
    }
}
