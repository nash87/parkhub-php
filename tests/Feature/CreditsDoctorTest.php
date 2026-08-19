<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `parkhub:credits-doctor` — tell an operator whether the credit ledger has
 * been abused, and let them repair it with an audit trail.
 *
 * Until the cancellation path was fixed, repeating `DELETE /bookings/{id}`
 * credited the caller every time, and `POST /bookings/quick` created
 * bookings without ever taking a credit. Both leave a signature in
 * `credit_transactions`, and an instance that ran the old code may still be
 * carrying the result. Upgrading stops the bleeding; it does not tell you
 * whether you bled.
 */
class CreditsDoctorTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(User $user, string $bookingId, string $type, int $amount): CreditTransaction
    {
        return CreditTransaction::create([
            'user_id' => $user->id,
            'booking_id' => $bookingId,
            'amount' => $amount,
            'type' => $type,
            'description' => 'test',
        ]);
    }

    public function test_a_clean_ledger_reports_nothing(): void
    {
        $user = User::factory()->create(['credits_balance' => 5]);
        $this->ledger($user, 'b-1', 'deduction', -1);
        $this->ledger($user, 'b-1', 'refund', 1);

        $this->artisan('parkhub:credits-doctor')
            ->expectsOutputToContain('No credit-ledger anomalies found.')
            ->assertExitCode(0);
    }

    /** The signature of the repeated-cancellation hole. */
    public function test_it_reports_a_booking_refunded_more_than_once(): void
    {
        $user = User::factory()->create(['credits_balance' => 9]);
        $this->ledger($user, 'b-2', 'deduction', -1);
        $this->ledger($user, 'b-2', 'refund', 1);
        $this->ledger($user, 'b-2', 'refund', 1);
        $this->ledger($user, 'b-2', 'refund', 1);

        $this->artisan('parkhub:credits-doctor')
            ->expectsOutputToContain('refunded more than once')
            ->assertExitCode(1);
    }

    /** The signature of refunding a booking that never paid. */
    public function test_it_reports_a_refund_with_no_matching_deduction(): void
    {
        $user = User::factory()->create(['credits_balance' => 6]);
        $this->ledger($user, 'b-3', 'refund', 1);

        $this->artisan('parkhub:credits-doctor')
            ->expectsOutputToContain('no matching deduction')
            ->assertExitCode(1);
    }

    public function test_it_reports_a_negative_balance(): void
    {
        User::factory()->create(['credits_balance' => -3]);

        $this->artisan('parkhub:credits-doctor')
            ->expectsOutputToContain('negative credit balance')
            ->assertExitCode(1);
    }

    /**
     * Repair must be auditable. Clamping a balance without recording why is
     * how you turn a detectable problem into an invisible one.
     */
    public function test_repair_zeroes_a_negative_balance_and_records_why(): void
    {
        $user = User::factory()->create(['credits_balance' => -3]);

        $this->artisan('parkhub:credits-doctor --repair')->assertExitCode(0);

        $this->assertSame(0, $user->fresh()->credits_balance);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'adjustment',
            'amount' => 3,
        ]);
    }

    public function test_repair_is_not_performed_without_the_flag(): void
    {
        $user = User::factory()->create(['credits_balance' => -3]);

        $this->artisan('parkhub:credits-doctor')->assertExitCode(1);

        $this->assertSame(-3, $user->fresh()->credits_balance, 'the doctor repaired without being asked');
    }
}
