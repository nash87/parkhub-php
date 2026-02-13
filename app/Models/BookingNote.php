<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BookingNote extends Model
{
    use HasUuids;
    protected $fillable = ['booking_id', 'user_id', 'note'];
}
