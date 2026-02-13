<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'endpoint', 'p256dh', 'auth'];
}
