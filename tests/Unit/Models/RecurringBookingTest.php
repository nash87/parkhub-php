<?php

namespace Tests\Unit\Models;

use App\Models\RecurringBooking;
use Tests\TestCase;

class RecurringBookingTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $rb = new RecurringBooking;
        $fillable = $rb->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('lot_id', $fillable);
        $this->assertContains('slot_id', $fillable);
        $this->assertContains('days_of_week', $fillable);
        $this->assertContains('start_date', $fillable);
        $this->assertContains('end_date', $fillable);
        $this->assertContains('start_time', $fillable);
        $this->assertContains('end_time', $fillable);
        $this->assertContains('vehicle_plate', $fillable);
        $this->assertContains('active', $fillable);
    }
}
