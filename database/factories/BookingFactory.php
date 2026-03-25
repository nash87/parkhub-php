<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lot_id' => ParkingLot::factory(),
            'slot_id' => ParkingSlot::factory(),
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'single',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_ACTIVE]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_CANCELLED]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_COMPLETED]);
    }

    public function noShow(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_NO_SHOW]);
    }
}
