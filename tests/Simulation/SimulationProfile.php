<?php

namespace Tests\Simulation;

class SimulationProfile
{
    public function __construct(
        public readonly string $name,
        public readonly int $lotCount,
        public readonly int $slotsPerLot,
        public readonly int $userCount,
        public readonly int $bookingsPerDay,
        public readonly int $days,
        public readonly float $cancellationRate,
        public readonly float $recurringRate,
        public readonly float $waitlistRate,
        public readonly float $conflictRate,
    ) {}

    public static function small(): self
    {
        return new self(
            name: 'small',
            lotCount: 1,
            slotsPerLot: 200,
            userCount: 500,
            bookingsPerDay: 50,
            days: 30,
            cancellationRate: 0.15,
            recurringRate: 0.05,
            waitlistRate: 0.03,
            conflictRate: 0.02,
        );
    }

    public static function campus(): self
    {
        return new self(
            name: 'campus',
            lotCount: 3,
            slotsPerLot: 267, // ~800 total
            userCount: 2000,
            bookingsPerDay: 200,
            days: 30,
            cancellationRate: 0.15,
            recurringRate: 0.05,
            waitlistRate: 0.03,
            conflictRate: 0.02,
        );
    }

    public static function enterprise(): self
    {
        return new self(
            name: 'enterprise',
            lotCount: 5,
            slotsPerLot: 400, // 2000 total
            userCount: 5000,
            bookingsPerDay: 500,
            days: 30,
            cancellationRate: 0.15,
            recurringRate: 0.05,
            waitlistRate: 0.03,
            conflictRate: 0.02,
        );
    }

    public function totalSlots(): int
    {
        return $this->lotCount * $this->slotsPerLot;
    }

    public function totalBookings(): int
    {
        return $this->bookingsPerDay * $this->days;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'lot_count' => $this->lotCount,
            'slots_per_lot' => $this->slotsPerLot,
            'total_slots' => $this->totalSlots(),
            'user_count' => $this->userCount,
            'bookings_per_day' => $this->bookingsPerDay,
            'days' => $this->days,
            'total_bookings' => $this->totalBookings(),
            'cancellation_rate' => $this->cancellationRate,
            'recurring_rate' => $this->recurringRate,
            'waitlist_rate' => $this->waitlistRate,
            'conflict_rate' => $this->conflictRate,
        ];
    }
}
