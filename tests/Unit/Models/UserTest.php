<?php

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $user = new User();
        $fillable = $user->getFillable();

        $this->assertContains('username', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('phone', $fillable);
        $this->assertContains('preferences', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('department', $fillable);
        $this->assertContains('credits_balance', $fillable);
        $this->assertContains('credits_monthly_quota', $fillable);
    }

    public function test_hidden_attributes(): void
    {
        $user = new User();
        $hidden = $user->getHidden();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_true_for_superadmin_role(): void
    {
        $user = new User();
        $user->role = 'superadmin';

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_user_role(): void
    {
        $user = new User();
        $user->role = 'user';

        $this->assertFalse($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_premium_role(): void
    {
        $user = new User();
        $user->role = 'premium';

        $this->assertFalse($user->isAdmin());
    }

    public function test_is_premium_returns_true_for_premium_role(): void
    {
        $user = new User();
        $user->role = 'premium';

        $this->assertTrue($user->isPremium());
    }

    public function test_is_premium_returns_false_for_user_role(): void
    {
        $user = new User();
        $user->role = 'user';

        $this->assertFalse($user->isPremium());
    }

    public function test_is_premium_returns_false_for_admin_role(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertFalse($user->isPremium());
    }

    public function test_bookings_relation_defined(): void
    {
        $user = new User();
        $relation = $user->bookings();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_vehicles_relation_defined(): void
    {
        $user = new User();
        $relation = $user->vehicles();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_absences_relation_defined(): void
    {
        $user = new User();
        $relation = $user->absences();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_favorites_relation_defined(): void
    {
        $user = new User();
        $relation = $user->favorites();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_recurring_bookings_relation_defined(): void
    {
        $user = new User();
        $relation = $user->recurringBookings();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_credit_transactions_relation_defined(): void
    {
        $user = new User();
        $relation = $user->creditTransactions();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }
}
