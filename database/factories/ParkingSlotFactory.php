<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParkingSlotFactory extends Factory
{
    protected $model = ParkingSlot::class;

    public function definition(): array
    {
        return [
            'lot_id' => ParkingLot::factory(),
            'slot_number' => strtoupper(fake()->randomLetter()).fake()->numberBetween(1, 99),
            'status' => 'available',
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => 'occupied']);
    }

    public function accessible(): static
    {
        return $this->state(fn () => ['is_accessible' => true]);
    }
}
