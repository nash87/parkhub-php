<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-1/P1-2 — No-show auto-release + waitlist auto-promotion.
 *
 * 1. Per-lot config columns on parking_lots:
 *    - check_in_deadline_minutes: minutes after start_time before a
 *      un-checked-in booking is released as no-show (null = global default 30,
 *      0 = feature disabled for this lot).
 *    - claim_window_minutes: minutes a waitlist offer stays open before it
 *      expires and propagates to the next FIFO entry (null = default 15).
 *
 * 2. waitlist_offers table: tracks a concrete slot offer to one waitlist
 *    entrant after a no-show release. A separate model avoids overloading
 *    WaitlistEntry (which tracks queue position) with ephemeral offer state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->unsignedSmallInteger('check_in_deadline_minutes')->nullable()->after('dynamic_pricing_rules');
            $table->unsignedSmallInteger('claim_window_minutes')->nullable()->after('check_in_deadline_minutes');
        });

        Schema::create('waitlist_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('waitlist_entry_id')->constrained('waitlist_entries')->cascadeOnDelete();
            $table->foreignUuid('released_booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->foreignUuid('slot_id')->constrained('parking_slots')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // pending → claimed | expired | declined
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->foreignUuid('claimed_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['lot_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_offers');

        Schema::table('parking_lots', function (Blueprint $table) {
            $table->dropColumn(['check_in_deadline_minutes', 'claim_window_minutes']);
        });
    }
};
