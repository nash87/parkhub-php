<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared calendar visibility (nash87/parkhub-php#571) plus two contract
 * defects found in the same endpoint:
 *
 *  - the SPA sends `?start=&end=` while the controller only ever read
 *    `from`/`to`, so month navigation silently returned the current month;
 *  - the range filter required full containment, so any booking crossing a
 *    range boundary disappeared from the view.
 */
class CalendarSharedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private ParkingLot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lot = ParkingLot::create([
            'name' => 'Shared Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
    }

    private function booking(User $user, string $slotNumber, $start, $end, string $status = 'confirmed'): Booking
    {
        $slot = ParkingSlot::create([
            'lot_id' => $this->lot->id,
            'slot_number' => $slotNumber,
            'status' => 'available',
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'lot_id' => $this->lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $this->lot->name,
            'slot_number' => $slotNumber,
            'start_time' => $start,
            'end_time' => $end,
            'booking_type' => 'single',
            'status' => $status,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * The SPA calls `/api/v1/calendar/events?start=…&end=…`. The controller
     * only read `from`/`to`, so it always fell back to the current month and
     * every other month rendered empty.
     */
    public function test_honours_the_start_and_end_parameters_the_spa_actually_sends(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $nextMonth = now()->addMonthNoOverflow()->startOfMonth()->addDays(3)->setHour(9);
        $this->booking($user, 'N1', $nextMonth, (clone $nextMonth)->addHours(4));

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($user))
            ->getJson('/api/v1/calendar/events'
                .'?start='.now()->addMonthNoOverflow()->startOfMonth()->toDateTimeString()
                .'&end='.now()->addMonthNoOverflow()->endOfMonth()->toDateTimeString());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'), 'The start/end parameters sent by the SPA were ignored.');
    }

    /** The legacy `from`/`to` spelling must keep working. */
    public function test_still_honours_the_legacy_from_and_to_parameters(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $nextMonth = now()->addMonthNoOverflow()->startOfMonth()->addDays(3)->setHour(9);
        $this->booking($user, 'N2', $nextMonth, (clone $nextMonth)->addHours(4));

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($user))
            ->getJson('/api/v1/calendar/events'
                .'?from='.now()->addMonthNoOverflow()->startOfMonth()->toDateTimeString()
                .'&to='.now()->addMonthNoOverflow()->endOfMonth()->toDateTimeString());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * A booking that starts before the window and ends after it overlaps
     * every day in view, yet a containment filter dropped it entirely.
     */
    public function test_includes_bookings_that_straddle_the_range_boundary(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->booking(
            $user,
            'S1',
            now()->startOfMonth()->subDays(2)->setHour(8),
            now()->startOfMonth()->addDays(2)->setHour(18),
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($user))
            ->getJson('/api/v1/calendar/events'
                .'?start='.now()->startOfMonth()->toDateTimeString()
                .'&end='.now()->endOfMonth()->toDateTimeString());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'), 'A booking overlapping the window was dropped by a containment filter.');
    }

    public function test_defaults_to_only_the_callers_own_bookings(): void
    {
        $me = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $day = now()->startOfMonth()->addDays(4)->setHour(9);
        $this->booking($me, 'M1', $day, (clone $day)->addHours(3));
        $this->booking($other, 'O1', $day, (clone $day)->addHours(3));

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/v1/calendar/events'
                .'?start='.now()->startOfMonth()->toDateTimeString()
                .'&end='.now()->endOfMonth()->toDateTimeString());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'), 'The default scope must not widen to other users.');
        $this->assertTrue($response->json('data')[0]['mine']);
    }

    public function test_scope_all_returns_other_users_bookings(): void
    {
        $me = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user', 'name' => 'Dana Meyer']);
        $day = now()->startOfMonth()->addDays(4)->setHour(9);
        $this->booking($me, 'M2', $day, (clone $day)->addHours(3));
        $this->booking($other, 'O2', $day, (clone $day)->addHours(3));

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/v1/calendar/events?scope=all'
                .'&start='.now()->startOfMonth()->toDateTimeString()
                .'&end='.now()->endOfMonth()->toDateTimeString());

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        $mine = collect($response->json('data'))->firstWhere('mine', true);
        $theirs = collect($response->json('data'))->firstWhere('mine', false);
        $this->assertNotNull($mine);
        $this->assertNotNull($theirs);
        $this->assertSame('O2', $theirs['slot_number']);
    }

    /**
     * Widening the calendar must not widen what it discloses. The existing
     * `booking_visibility` setting governs the owner label, exactly as it
     * already does for the team view.
     */
    public function test_booking_visibility_initials_masks_the_owner_name(): void
    {
        Setting::set('booking_visibility', 'initials');

        $me = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user', 'name' => 'Dana Meyer']);
        $day = now()->startOfMonth()->addDays(4)->setHour(9);
        $this->booking($other, 'O3', $day, (clone $day)->addHours(3));

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/v1/calendar/events?scope=all'
                .'&start='.now()->startOfMonth()->toDateTimeString()
                .'&end='.now()->endOfMonth()->toDateTimeString());

        $response->assertOk();
        $theirs = collect($response->json('data'))->firstWhere('mine', false);
        $this->assertSame('D.M', $theirs['owner']);
        $this->assertStringNotContainsString('Dana', json_encode($response->json('data')));
    }

    public function test_booking_visibility_occupied_hides_identity_entirely(): void
    {
        Setting::set('booking_visibility', 'occupied');

        $me = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user', 'name' => 'Dana Meyer']);
        $day = now()->startOfMonth()->addDays(4)->setHour(9);
        $this->booking($other, 'O4', $day, (clone $day)->addHours(3));

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/v1/calendar/events?scope=all'
                .'&start='.now()->startOfMonth()->toDateTimeString()
                .'&end='.now()->endOfMonth()->toDateTimeString());

        $theirs = collect($response->json('data'))->firstWhere('mine', false);
        $this->assertSame('Occupied', $theirs['owner']);
    }

    /** Other people's bookings must never carry plate or notes. */
    public function test_scope_all_never_leaks_vehicle_plate_or_notes(): void
    {
        $me = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $day = now()->startOfMonth()->addDays(4)->setHour(9);
        $b = $this->booking($other, 'O5', $day, (clone $day)->addHours(3));
        $b->update(['vehicle_plate' => 'B-XX-9999', 'notes' => 'private note']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/v1/calendar/events?scope=all'
                .'&start='.now()->startOfMonth()->toDateTimeString()
                .'&end='.now()->endOfMonth()->toDateTimeString());

        $body = json_encode($response->json('data'));
        $this->assertStringNotContainsString('B-XX-9999', $body);
        $this->assertStringNotContainsString('private note', $body);
    }

    public function test_scope_all_excludes_cancelled_bookings(): void
    {
        $me = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $day = now()->startOfMonth()->addDays(4)->setHour(9);
        $this->booking($other, 'O6', $day, (clone $day)->addHours(3), 'cancelled');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/v1/calendar/events?scope=all'
                .'&start='.now()->startOfMonth()->toDateTimeString()
                .'&end='.now()->endOfMonth()->toDateTimeString());

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * The privacy control that governs all of the above was readable by
     * TeamController but absent from the settings defaults, the write
     * allowlist and the request validation — so no admin could ever see or
     * change it.
     */
    public function test_booking_visibility_is_an_admin_configurable_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $this->token($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.booking_visibility', 'full');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/settings', ['booking_visibility' => 'initials'])
            ->assertOk();

        $this->assertSame('initials', Setting::get('booking_visibility'));
    }

    public function test_booking_visibility_rejects_an_unknown_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withHeader('Authorization', 'Bearer '.$this->token($admin))
            ->putJson('/api/v1/admin/settings', ['booking_visibility' => 'everything'])
            ->assertStatus(422);
    }
}
