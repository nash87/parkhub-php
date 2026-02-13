<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RecurringBooking extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'lot_id', 'slot_id', 'days_of_week', 'start_date', 'end_date',
        'start_time', 'end_time', 'vehicle_plate', 'active',
    ];

    protected function casts(): array
    {
        return ['days_of_week' => 'array', 'active' => 'boolean'];
    }
}
