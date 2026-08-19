<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\SendPushNotificationJob;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property ?string $title
 * @property ?string $message
 * @property ?array<string, mixed> $data
 * @property bool $read
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications_custom';

    protected $fillable = ['user_id', 'type', 'title', 'message', 'data', 'read'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::created(function (Notification $notification) {
            // Queue the push rather than performing it here. This hook runs
            // inside whatever request created the notification, and the send
            // is a blocking round-trip to every registered endpoint
            // (web-push's `flush()` is `yield $promise->wait()`). The comment
            // this replaces called it "non-blocking"; it never was.
            SendPushNotificationJob::dispatch(
                $notification->user_id,
                $notification->title ?? 'ParkHub',
                $notification->message ?? '',
            );
        });
    }
}
