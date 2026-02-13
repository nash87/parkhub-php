<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify existing users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('id')->nullable();
            $table->string('phone')->nullable()->after('email');
            $table->string('picture')->nullable()->after('phone');
            $table->string('role')->default('user')->after('password');
            $table->json('preferences')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('preferences');
            $table->string('department')->nullable()->after('is_active');
            $table->timestamp('last_login')->nullable()->after('department');
        });

        // Parking lots
        Schema::create('parking_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('address')->nullable();
            $table->integer('total_slots')->default(0);
            $table->integer('available_slots')->default(0);
            $table->json('layout')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        // Zones
        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lot_id');
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->foreign('lot_id')->references('id')->on('parking_lots')->onDelete('cascade');
        });

        // Parking slots
        Schema::create('parking_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lot_id');
            $table->string('slot_number');
            $table->string('status')->default('available');
            $table->string('reserved_for_department')->nullable();
            $table->uuid('zone_id')->nullable();
            $table->timestamps();
            $table->foreign('lot_id')->references('id')->on('parking_lots')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('set null');
        });

        // Bookings
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('lot_id');
            $table->uuid('slot_id');
            $table->string('booking_type')->default('einmalig');
            $table->string('lot_name')->nullable();
            $table->string('slot_number')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('status')->default('confirmed');
            $table->text('notes')->nullable();
            $table->json('recurrence')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lot_id')->references('id')->on('parking_lots')->onDelete('cascade');
            $table->foreign('slot_id')->references('id')->on('parking_slots')->onDelete('cascade');
            $table->index(['slot_id', 'start_time', 'end_time']);
            $table->index(['user_id', 'status']);
        });

        // Vehicles
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('plate');
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('photo_url')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Absences
        Schema::create('absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('absence_type'); // homeoffice, vacation, sick, training, other
            $table->date('start_date');
            $table->date('end_date');
            $table->text('note')->nullable();
            $table->string('source')->default('manual'); // manual, import, pattern
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'start_date', 'end_date']);
        });

        // Settings
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Audit log
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('action');
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->index('action');
            $table->index('created_at');
        });

        // Announcements
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('message');
            $table->string('severity')->default('info');
            $table->boolean('active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        // Notifications
        Schema::create('notifications_custom', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'read']);
        });

        // Favorites
        Schema::create('favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('slot_id');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('slot_id')->references('id')->on('parking_slots')->onDelete('cascade');
            $table->unique(['user_id', 'slot_id']);
        });

        // Recurring bookings
        Schema::create('recurring_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('lot_id');
            $table->uuid('slot_id');
            $table->json('days_of_week'); // [0=Mon..6=Sun]
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('start_time')->default('08:00');
            $table->string('end_time')->default('18:00');
            $table->string('vehicle_plate')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Guest bookings
        Schema::create('guest_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by');
            $table->uuid('lot_id');
            $table->uuid('slot_id');
            $table->string('guest_name');
            $table->string('guest_code')->unique();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('vehicle_plate')->nullable();
            $table->string('status')->default('confirmed');
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

        // Booking notes
        Schema::create('booking_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('booking_id');
            $table->uuid('user_id');
            $table->text('note');
            $table->timestamps();
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
        });

        // Push subscriptions
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('endpoint');
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Webhooks
        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('url');
            $table->json('events')->nullable();
            $table->string('secret')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('booking_notes');
        Schema::dropIfExists('guest_bookings');
        Schema::dropIfExists('recurring_bookings');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('notifications_custom');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('absences');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('parking_slots');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('parking_lots');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'phone', 'picture', 'role', 'preferences', 'is_active', 'department', 'last_login']);
        });
    }
};
