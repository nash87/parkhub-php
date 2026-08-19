<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record that a booking reminder has been sent.
 *
 * `SendBookingReminderJob` selects every confirmed booking starting inside
 * its look-ahead window and mails each one. It is a queued job, so it
 * retries, and once it is on a schedule it runs on a cadence shorter than
 * that window — both of which re-send. Without a record of what already
 * went out, turning reminders on means sending each user several.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
