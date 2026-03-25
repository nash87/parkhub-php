<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['lot_id', 'name', 'color', 'description', 'tier', 'pricing_multiplier', 'max_capacity'];

    public function lot()
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function slots()
    {
        return $this->hasMany(ParkingSlot::class);
    }
}
