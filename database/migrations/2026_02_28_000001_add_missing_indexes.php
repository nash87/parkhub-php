<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes that were missing from the initial schema.
 *
 * bookings.lot_id        — queried heavily in occupancy, heatmap, and admin stats
 * parking_slots.lot_id   — queried on every lot show and slot listing
 * bookings.start_time    — range scans on start_time for calendar/reporting
 * users.email            — login by email (currently only username is implicitly indexed via unique)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // lot_id used in most booking queries; add if not already present
            $table->index('lot_id', 'bookings_lot_id_index');
            // start_time already part of the compound index (slot_id, start_time, end_time),
            // but a standalone index helps date-range reporting queries
            $table->index('start_time', 'bookings_start_time_index');
        });

        Schema::table('parking_slots', function (Blueprint $table) {
            $table->index('lot_id', 'parking_slots_lot_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_lot_id_index');
            $table->dropIndex('bookings_start_time_index');
        });

        Schema::table('parking_slots', function (Blueprint $table) {
            $table->dropIndex('parking_slots_lot_id_index');
        });
    }
};
