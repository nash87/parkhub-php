<?php

namespace Tests\Unit\Models;

use App\Models\ParkingLot;
use PHPUnit\Framework\TestCase;

class ParkingLotTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $lot = new ParkingLot();
        $fillable = $lot->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('address', $fillable);
        $this->assertContains('total_slots', $fillable);
        $this->assertContains('available_slots', $fillable);
        $this->assertContains('layout', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('hourly_rate', $fillable);
        $this->assertContains('daily_max', $fillable);
        $this->assertContains('monthly_pass', $fillable);
        $this->assertContains('currency', $fillable);
    }

    public function test_slots_relation_defined(): void
    {
        $lot = new ParkingLot();
        $relation = $lot->slots();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_zones_relation_defined(): void
    {
        $lot = new ParkingLot();
        $relation = $lot->zones();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }

    public function test_bookings_relation_defined(): void
    {
        $lot = new ParkingLot();
        $relation = $lot->bookings();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
    }
}
