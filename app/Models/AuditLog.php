<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_log';
    protected $fillable = ['user_id', 'username', 'action', 'details', 'ip_address'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
