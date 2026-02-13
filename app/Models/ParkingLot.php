<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ParkingLot extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'address', 'total_slots', 'available_slots', 'layout', 'status'];

    protected function casts(): array
    {
        return ['layout' => 'array', 'total_slots' => 'integer', 'available_slots' => 'integer'];
    }

    public function slots() { return $this->hasMany(ParkingSlot::class, 'lot_id'); }
    public function zones() { return $this->hasMany(Zone::class, 'lot_id'); }
    public function bookings() { return $this->hasMany(Booking::class, 'lot_id'); }
}
