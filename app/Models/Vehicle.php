<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'plate', 'make', 'model', 'color', 'is_default', 'photo_url'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user() { return $this->belongsTo(User::class); }
}
