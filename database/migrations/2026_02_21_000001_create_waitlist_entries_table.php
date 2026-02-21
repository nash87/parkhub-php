<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->foreignUuid('slot_id')->nullable()->constrained('parking_slots')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
