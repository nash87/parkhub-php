<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'absence_type', 'start_date', 'end_date', 'note', 'source'];

    public function user() { return $this->belongsTo(User::class); }
}
