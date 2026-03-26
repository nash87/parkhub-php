<?php

namespace Tests\Unit\Policies;

use App\Models\Absence;
use App\Models\User;
use App\Policies\AbsencePolicy;
use Tests\TestCase;

class AbsencePolicyTest extends TestCase
{
    private AbsencePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AbsencePolicy;
    }

    private function makeUser(string $role = 'user'): User
    {
        $user = new User;
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->role = $role;

        return $user;
    }

    private function makeAbsence(string $userId): Absence
    {
        $absence = new Absence;
        $absence->user_id = $userId;

        return $absence;
    }

    public function test_owner_can_update_absence(): void
    {
        $user = $this->makeUser();
        $absence = $this->makeAbsence($user->id);

        $this->assertTrue($this->policy->update($user, $absence));
    }

    public function test_non_owner_cannot_update_absence(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $absence = $this->makeAbsence($owner->id);

        $this->assertFalse($this->policy->update($other, $absence));
    }

    public function test_owner_can_delete_absence(): void
    {
        $user = $this->makeUser();
        $absence = $this->makeAbsence($user->id);

        $this->assertTrue($this->policy->delete($user, $absence));
    }

    public function test_non_owner_cannot_delete_absence(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $absence = $this->makeAbsence($owner->id);

        $this->assertFalse($this->policy->delete($other, $absence));
    }
}
