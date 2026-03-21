<?php

namespace Tests\Unit\Models;

use App\Models\ParkingSlot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class ParkingSlotTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $slot = new ParkingSlot;
        $fillable = $slot->getFillable();

        $this->assertContains('lot_id', $fillable);
        $this->assertContains('slot_number', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('slot_type', $fillable);
        $this->assertContains('features', $fillable);
        $this->assertContains('reserved_for_department', $fillable);
        $this->assertContains('zone_id', $fillable);
    }

    public function test_lot_relation_defined(): void
    {
        $slot = new ParkingSlot;
        $relation = $slot->lot();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_zone_relation_defined(): void
    {
        $slot = new ParkingSlot;
        $relation = $slot->zone();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_bookings_relation_defined(): void
    {
        $slot = new ParkingSlot;
        $relation = $slot->bookings();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_active_booking_relation_defined(): void
    {
        $slot = new ParkingSlot;
        $relation = $slot->activeBooking();
        $this->assertInstanceOf(HasOne::class, $relation);
    }
}
