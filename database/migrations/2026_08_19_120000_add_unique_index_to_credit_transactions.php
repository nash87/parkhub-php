<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A booking may be debited once and refunded once — never twice.
 *
 * `credit_transactions` indexed only `user_id` and `type`, so nothing
 * stopped a second `refund` row for the same booking. Cancelling the same
 * booking repeatedly credited the caller each time. Application-side checks
 * can race; this makes the invariant the database's problem.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Collapse any duplicates a pre-fix instance already accumulated,
        // keeping the earliest row of each (booking_id, type) pair —
        // otherwise the index cannot be created. Rows with a null
        // booking_id (monthly refills, admin grants) are not constrained.
        $duplicateIds = DB::table('credit_transactions')
            ->select('id')
            ->whereNotNull('booking_id')
            ->whereIn('id', function ($query) {
                $query->select('ct.id')
                    ->from('credit_transactions as ct')
                    ->join(DB::raw('(SELECT booking_id, type, MIN(created_at) AS first_at
                                     FROM credit_transactions
                                     WHERE booking_id IS NOT NULL
                                     GROUP BY booking_id, type) AS keep'), function ($join) {
                        $join->on('ct.booking_id', '=', 'keep.booking_id')
                            ->on('ct.type', '=', 'keep.type')
                            ->on('ct.created_at', '>', 'keep.first_at');
                    });
            })
            ->pluck('id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('credit_transactions')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->unique(['booking_id', 'type'], 'credit_transactions_booking_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropUnique('credit_transactions_booking_type_unique');
        });
    }
};
