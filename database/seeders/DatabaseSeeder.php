<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Default:  php artisan db:seed          → minimal dev seed
     * Demo:     DEMO_MODE=true db:seed       → full production simulation
     *                                          (admin user, lots, bookings,
     *                                          sufficient data for the full
     *                                          E2E / visual-regression suite)
     * Full sim: php artisan db:seed --class=ProductionSimulationSeeder
     */
    public function run(): void
    {
        if (config('parkhub.demo_mode') || env('DEMO_MODE') === 'true') {
            $this->call(ProductionSimulationSeeder::class);

            return;
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
