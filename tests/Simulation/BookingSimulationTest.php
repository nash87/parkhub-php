<?php

namespace Tests\Simulation;

class BookingSimulationTest extends SimulationTestCase
{
    /**
     * Small profile: 1 lot, 200 slots, 500 users, 50 bookings/day, 30 days.
     *
     * @group simulation
     */
    public function test_small_profile_30_day_simulation(): void
    {
        $this->runSimulation('small');
    }

    /**
     * Campus profile: 3 lots, ~800 slots, 2000 users, 200 bookings/day, 30 days.
     *
     * @group simulation
     */
    public function test_campus_profile_30_day_simulation(): void
    {
        $this->runSimulation('campus');
    }

    /**
     * Enterprise profile: 5 lots, 2000 slots, 5000 users, 500 bookings/day, 30 days.
     *
     * @group simulation
     */
    public function test_enterprise_profile_30_day_simulation(): void
    {
        $this->runSimulation('enterprise');
    }
}
