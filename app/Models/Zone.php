<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasUuids;
    protected $fillable = ['lot_id', 'name', 'color', 'description'];

    public function lot() { return $this->belongsTo(ParkingLot::class, 'lot_id'); }
    public function slots() { return $this->hasMany(ParkingSlot::class); }
}
