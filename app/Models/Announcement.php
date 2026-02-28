<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'message', 'severity', 'active', 'created_by', 'expires_at'];

    protected function casts(): array
    {
        return [
            'active'     => 'boolean',
            'expires_at' => 'datetime',
        ];
    }
}
