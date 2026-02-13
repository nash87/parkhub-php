<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'slot_id'];

    public function slot() { return $this->belongsTo(ParkingSlot::class, 'slot_id'); }
}
