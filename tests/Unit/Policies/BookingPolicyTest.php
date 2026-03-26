<?php

namespace Tests\Unit\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    private BookingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new BookingPolicy;
    }

    private function makeUser(string $role = 'user'): User
    {
        $user = new User;
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->role = $role;

        return $user;
    }

    private function makeBooking(string $userId): Booking
    {
        $booking = new Booking;
        $booking->user_id = $userId;

        return $booking;
    }

    public function test_owner_can_view_booking(): void
    {
        $user = $this->makeUser();
        $booking = $this->makeBooking($user->id);

        $this->assertTrue($this->policy->view($user, $booking));
    }

    public function test_non_owner_cannot_view_booking(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $booking = $this->makeBooking($owner->id);

        $this->assertFalse($this->policy->view($other, $booking));
    }

    public function test_owner_can_update_booking(): void
    {
        $user = $this->makeUser();
        $booking = $this->makeBooking($user->id);

        $this->assertTrue($this->policy->update($user, $booking));
    }

    public function test_non_owner_cannot_update_booking(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $booking = $this->makeBooking($owner->id);

        $this->assertFalse($this->policy->update($other, $booking));
    }

    public function test_owner_can_delete_booking(): void
    {
        $user = $this->makeUser();
        $booking = $this->makeBooking($user->id);

        $this->assertTrue($this->policy->delete($user, $booking));
    }

    public function test_non_owner_cannot_delete_booking(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $booking = $this->makeBooking($owner->id);

        $this->assertFalse($this->policy->delete($other, $booking));
    }

    public function test_owner_can_update_booking_notes(): void
    {
        $user = $this->makeUser();
        $booking = $this->makeBooking($user->id);

        $this->assertTrue($this->policy->updateNotes($user, $booking));
    }

    public function test_admin_can_update_any_booking_notes(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser();
        $booking = $this->makeBooking($owner->id);

        $this->assertTrue($this->policy->updateNotes($admin, $booking));
    }

    public function test_non_owner_non_admin_cannot_update_booking_notes(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $booking = $this->makeBooking($owner->id);

        $this->assertFalse($this->policy->updateNotes($other, $booking));
    }
}
