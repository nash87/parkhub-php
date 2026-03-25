<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition(): array
    {
        return [
            'lot_id' => ParkingLot::factory(),
            'name' => 'Zone '.fake()->randomLetter(),
            'color' => fake()->hexColor(),
            'description' => fake()->sentence(),
        ];
    }
}
