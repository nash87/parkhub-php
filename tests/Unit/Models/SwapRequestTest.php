<?php

namespace Tests\Unit\Models;

use App\Models\SwapRequest;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class SwapRequestTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $swap = new SwapRequest;
        $fillable = $swap->getFillable();

        $this->assertContains('requester_booking_id', $fillable);
        $this->assertContains('target_booking_id', $fillable);
        $this->assertContains('requester_id', $fillable);
        $this->assertContains('target_id', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('message', $fillable);
    }

    public function test_requester_booking_relation_defined(): void
    {
        $swap = new SwapRequest;
        $relation = $swap->requesterBooking();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_target_booking_relation_defined(): void
    {
        $swap = new SwapRequest;
        $relation = $swap->targetBooking();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_requester_relation_defined(): void
    {
        $swap = new SwapRequest;
        $relation = $swap->requester();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_target_relation_defined(): void
    {
        $swap = new SwapRequest;
        $relation = $swap->target();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }
}
