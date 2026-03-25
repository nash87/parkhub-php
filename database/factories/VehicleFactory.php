<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plate' => strtoupper(fake()->bothify('??-??-####')),
            'make' => fake()->randomElement(['BMW', 'Audi', 'Mercedes', 'VW', 'Tesla']),
            'model' => fake()->word(),
            'color' => fake()->safeColorName(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
