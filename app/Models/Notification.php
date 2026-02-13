<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications_custom';
    protected $fillable = ['user_id', 'type', 'title', 'message', 'data', 'read'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read' => 'boolean'];
    }
}
