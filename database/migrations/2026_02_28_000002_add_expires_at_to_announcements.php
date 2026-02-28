<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add expires_at to announcements.
 *
 * The public /api/v1/announcements/active route filters by
 *   WHERE expires_at IS NULL OR expires_at > NOW()
 * so this column is required for the query to run without a SQL error.
 * Existing rows implicitly get NULL (never expires), which is the
 * correct default for previously created announcements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
