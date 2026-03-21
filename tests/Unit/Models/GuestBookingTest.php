<?php

namespace Tests\Unit\Models;

use App\Models\GuestBooking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class GuestBookingTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $gb = new GuestBooking;
        $fillable = $gb->getFillable();

        $this->assertContains('created_by', $fillable);
        $this->assertContains('lot_id', $fillable);
        $this->assertContains('slot_id', $fillable);
        $this->assertContains('guest_name', $fillable);
        $this->assertContains('guest_code', $fillable);
        $this->assertContains('start_time', $fillable);
        $this->assertContains('end_time', $fillable);
        $this->assertContains('vehicle_plate', $fillable);
        $this->assertContains('status', $fillable);
    }

    public function test_lot_relation_defined(): void
    {
        $gb = new GuestBooking;
        $relation = $gb->lot();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_slot_relation_defined(): void
    {
        $gb = new GuestBooking;
        $relation = $gb->slot();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_creator_relation_defined(): void
    {
        $gb = new GuestBooking;
        $relation = $gb->creator();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }
}
