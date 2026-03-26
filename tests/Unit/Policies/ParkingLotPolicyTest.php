<?php

namespace Tests\Unit\Policies;

use App\Models\ParkingLot;
use App\Models\User;
use App\Policies\ParkingLotPolicy;
use Tests\TestCase;

class ParkingLotPolicyTest extends TestCase
{
    private ParkingLotPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ParkingLotPolicy;
    }

    private function makeUser(string $role = 'user'): User
    {
        $user = new User;
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->role = $role;

        return $user;
    }

    public function test_admin_can_create_lot(): void
    {
        $admin = $this->makeUser('admin');

        $this->assertTrue($this->policy->create($admin));
    }

    public function test_superadmin_can_create_lot(): void
    {
        $superadmin = $this->makeUser('superadmin');

        $this->assertTrue($this->policy->create($superadmin));
    }

    public function test_regular_user_cannot_create_lot(): void
    {
        $user = $this->makeUser('user');

        $this->assertFalse($this->policy->create($user));
    }

    public function test_admin_can_update_lot(): void
    {
        $admin = $this->makeUser('admin');
        $lot = new ParkingLot;

        $this->assertTrue($this->policy->update($admin, $lot));
    }

    public function test_regular_user_cannot_update_lot(): void
    {
        $user = $this->makeUser('user');
        $lot = new ParkingLot;

        $this->assertFalse($this->policy->update($user, $lot));
    }

    public function test_admin_can_delete_lot(): void
    {
        $admin = $this->makeUser('admin');
        $lot = new ParkingLot;

        $this->assertTrue($this->policy->delete($admin, $lot));
    }

    public function test_regular_user_cannot_delete_lot(): void
    {
        $user = $this->makeUser('user');
        $lot = new ParkingLot;

        $this->assertFalse($this->policy->delete($user, $lot));
    }
}
