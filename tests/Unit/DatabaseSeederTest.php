<?php

namespace Tests\Unit;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionSimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_runs_production_simulation_when_demo_mode_enabled(): void
    {
        config(['parkhub.demo_mode' => true]);

        $seeder = $this->getMockBuilder(DatabaseSeeder::class)
            ->onlyMethods(['call'])
            ->getMock();

        $seeder->expects($this->once())
            ->method('call')
            ->with(ProductionSimulationSeeder::class);

        $seeder->run();
    }

    public function test_database_seeder_creates_minimal_seed_when_demo_mode_disabled(): void
    {
        config(['parkhub.demo_mode' => false]);

        (new DatabaseSeeder)->run();

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
    }
}
