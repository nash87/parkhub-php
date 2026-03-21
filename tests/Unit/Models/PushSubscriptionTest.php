<?php

namespace Tests\Unit\Models;

use App\Models\PushSubscription;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $sub = new PushSubscription;
        $fillable = $sub->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('endpoint', $fillable);
        $this->assertContains('p256dh', $fillable);
        $this->assertContains('auth', $fillable);
    }
}
