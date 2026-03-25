<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParkingLotFactory extends Factory
{
    protected $model = ParkingLot::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Parking',
            'address' => fake()->address(),
            'total_slots' => fake()->numberBetween(10, 200),
            'available_slots' => fake()->numberBetween(0, 200),
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }
}
