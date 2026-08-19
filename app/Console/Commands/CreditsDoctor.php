<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report — and optionally repair — anomalies in the credit ledger.
 *
 * Two defects made the ledger mintable before they were fixed: cancelling
 * a booking refunded unconditionally with no idempotency, and quick-book
 * created bookings without ever taking a credit. Upgrading stops the
 * bleeding; it does not tell an operator whether they bled, and a
 * self-hosted instance has nobody else to ask.
 *
 * Each check looks for the *signature* the corresponding hole leaves in
 * `credit_transactions`, so this is useful as a one-off after upgrading and
 * as a periodic sanity check afterwards.
 */
class CreditsDoctor extends Command
{
    protected $signature = 'parkhub:credits-doctor {--repair : Correct negative balances, recording an adjustment for each}';

    protected $description = 'Report anomalies in the credit ledger, and optionally repair negative balances';

    public function handle(): int
    {
        $findings = 0;

        $findings += $this->reportDoubleRefunds();
        $findings += $this->reportRefundsWithoutDeduction();
        $findings += $this->reportNegativeBalances();

        if ($findings === 0) {
            $this->info('No credit-ledger anomalies found.');

            return self::SUCCESS;
        }

        if ($this->option('repair')) {
            $repaired = $this->repairNegativeBalances();
            $this->info("Repaired {$repaired} negative balance(s); an adjustment row was written for each.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Re-run with --repair to zero negative balances (each one recorded as an adjustment).');
        $this->warn('The refund anomalies above are reported only: what to do about them is a business decision.');

        return self::FAILURE;
    }

    /**
     * A booking may be refunded at most once. More than one refund row for
     * the same booking is the signature of the repeated-cancellation hole.
     */
    private function reportDoubleRefunds(): int
    {
        // A query builder, not the model: these are aggregate rows, and
        // `refunds` / `credited` are column aliases rather than attributes.
        $rows = DB::table('credit_transactions')
            ->select('booking_id', DB::raw('COUNT(*) as refunds'), DB::raw('SUM(amount) as credited'))
            ->whereNotNull('booking_id')
            ->where('type', 'refund')
            ->groupBy('booking_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($rows as $row) {
            $this->error("Booking {$row->booking_id} was refunded more than once: {$row->refunds} refunds totalling {$row->credited} credits.");
        }

        return $rows->count();
    }

    /**
     * A refund without a matching deduction means credits were returned for
     * a booking that never cost any.
     */
    private function reportRefundsWithoutDeduction(): int
    {
        $paid = CreditTransaction::query()
            ->whereNotNull('booking_id')
            ->where('type', 'deduction')
            ->pluck('booking_id')
            ->all();

        $rows = CreditTransaction::query()
            ->whereNotNull('booking_id')
            ->where('type', 'refund')
            ->when($paid !== [], fn ($q) => $q->whereNotIn('booking_id', $paid))
            ->get();

        foreach ($rows as $row) {
            $this->error("Booking {$row->booking_id} was refunded with no matching deduction: {$row->amount} credits.");
        }

        return $rows->count();
    }

    /**
     * @return list<User>
     */
    private function negativeBalances(): array
    {
        /** @var list<User> $users */
        $users = User::query()
            ->where('credits_balance', '<', 0)
            ->get(['id', 'username', 'credits_balance'])
            ->all();

        return $users;
    }

    private function reportNegativeBalances(): int
    {
        $users = $this->negativeBalances();

        foreach ($users as $user) {
            $this->error("User {$user->username} has a negative credit balance: {$user->credits_balance}.");
        }

        return count($users);
    }

    /**
     * Zero the balance and record why.
     *
     * Clamping a balance without an audit row is how a detectable problem
     * becomes an invisible one, so the adjustment is written first and the
     * balance moved inside the same transaction.
     */
    private function repairNegativeBalances(): int
    {
        $repaired = 0;

        foreach ($this->negativeBalances() as $user) {
            $delta = -$user->credits_balance;

            DB::transaction(function () use ($user, $delta) {
                CreditTransaction::create([
                    'user_id' => $user->id,
                    'booking_id' => null,
                    'amount' => $delta,
                    'type' => 'adjustment',
                    'description' => 'credits-doctor: cleared a negative balance',
                ]);

                User::query()->whereKey($user->id)->update(['credits_balance' => 0]);
            });

            $repaired++;
        }

        return $repaired;
    }
}
