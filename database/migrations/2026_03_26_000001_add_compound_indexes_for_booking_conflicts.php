<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add compound indexes covering the hot-path slot-conflict queries in BookingController::store.
 *
 * bookings(slot_id, status, start_time, end_time)
 *   — covers: WHERE slot_id = ? AND status IN (…) AND start_time < ? AND end_time > ?
 *
 * bookings(lot_id, status, start_time, end_time)
 *   — covers: WHERE lot_id = ? AND status IN (…) AND start_time < ? AND end_time > ?
 *
 * bookings(user_id, status) already exists in the initial schema and is not duplicated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(
                ['slot_id', 'status', 'start_time', 'end_time'],
                'bookings_slot_status_start_end_index'
            );
            $table->index(
                ['lot_id', 'status', 'start_time', 'end_time'],
                'bookings_lot_status_start_end_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_slot_status_start_end_index');
            $table->dropIndex('bookings_lot_status_start_end_index');
        });
    }
};
