<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-user v5 customization settings (theme, sidebar variant, density,
 * font, feature toggles, notifications, privacy) as a JSON blob on the
 * users table.
 *
 * The schema is owned by the frontend (parkhub-web/src/design-v5/settings/
 * settings.ts) — the server stores an opaque JSON object and only validates
 * that it is JSON. Structural migration happens client-side via the
 * versioned `migrate()` helper, so server-side schema lock-in is avoided.
 *
 * `null` means "user hasn't customized yet" — clients fall back to defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
