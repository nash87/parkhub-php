<?php

namespace Tests\Unit\Models;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class BookingTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $booking = new Booking;
        $fillable = $booking->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('lot_id', $fillable);
        $this->assertContains('slot_id', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('start_time', $fillable);
        $this->assertContains('end_time', $fillable);
        $this->assertContains('vehicle_plate', $fillable);
        $this->assertContains('booking_type', $fillable);
        $this->assertContains('notes', $fillable);
        $this->assertContains('base_price', $fillable);
        $this->assertContains('tax_amount', $fillable);
        $this->assertContains('total_price', $fillable);
        $this->assertContains('currency', $fillable);
    }

    public function test_status_constant_confirmed(): void
    {
        $this->assertEquals('confirmed', Booking::STATUS_CONFIRMED);
    }

    public function test_status_constant_active(): void
    {
        $this->assertEquals('active', Booking::STATUS_ACTIVE);
    }

    public function test_status_constant_cancelled(): void
    {
        $this->assertEquals('cancelled', Booking::STATUS_CANCELLED);
    }

    public function test_status_constant_completed(): void
    {
        $this->assertEquals('completed', Booking::STATUS_COMPLETED);
    }

    public function test_status_constant_no_show(): void
    {
        $this->assertEquals('no_show', Booking::STATUS_NO_SHOW);
    }

    public function test_booking_has_user_relation_defined(): void
    {
        $booking = new Booking;
        $relation = $booking->user();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_booking_has_lot_relation_defined(): void
    {
        $booking = new Booking;
        $relation = $booking->lot();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_booking_has_slot_relation_defined(): void
    {
        $booking = new Booking;
        $relation = $booking->slot();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_booking_has_booking_notes_relation_defined(): void
    {
        $booking = new Booking;
        $relation = $booking->bookingNotes();
        $this->assertInstanceOf(HasMany::class, $relation);
    }
}
