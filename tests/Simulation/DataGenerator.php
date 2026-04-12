<?php

namespace Tests\Simulation;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DataGenerator
{
    private SimulationProfile $profile;

    /** @var Collection<int, User> */
    private Collection $users;

    /** @var Collection<int, ParkingLot> */
    private Collection $lots;

    /** @var Collection<int, ParkingSlot> */
    private Collection $slots;

    private static ?string $hashedPassword = null;

    public function __construct(SimulationProfile $profile)
    {
        $this->profile = $profile;
        $this->users = collect();
        $this->lots = collect();
        $this->slots = collect();
    }

    public function generateAll(): self
    {
        $this->generateUsers();
        $this->generateLots();

        return $this;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function getLots(): Collection
    {
        return $this->lots;
    }

    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function getRandomUser(): User
    {
        return $this->users->random();
    }

    public function getRandomSlot(?string $lotId = null): ParkingSlot
    {
        $pool = $lotId
            ? $this->slots->where('lot_id', $lotId)
            : $this->slots;

        return $pool->random();
    }

    public function getRandomLot(): ParkingLot
    {
        return $this->lots->random();
    }

    private function generateUsers(): void
    {
        static::$hashedPassword ??= Hash::make('SimUser123');

        $batchSize = min(100, $this->profile->userCount);
        $remaining = $this->profile->userCount;

        while ($remaining > 0) {
            $count = min($batchSize, $remaining);
            $batch = [];

            for ($i = 0; $i < $count; $i++) {
                $firstName = fake()->firstName();
                $lastName = fake()->lastName();
                $batch[] = [
                    'id' => Str::uuid()->toString(),
                    'name' => "{$firstName} {$lastName}",
                    'username' => strtolower($firstName).'_'.substr(Str::uuid()->toString(), 0, 8),
                    'email' => Str::uuid().'@sim.parkhub.test',
                    'email_verified_at' => now(),
                    'password' => static::$hashedPassword,
                    'role' => 'user',
                    'is_active' => true,
                    'preferences' => json_encode(['language' => 'en', 'theme' => 'system']),
                    'credits_balance' => rand(10, 100),
                    'credits_monthly_quota' => 50,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            User::insert($batch);
            $remaining -= $count;
        }

        $this->users = User::where('email', 'like', '%@sim.parkhub.test')
            ->limit($this->profile->userCount)
            ->get();
    }

    private function generateLots(): void
    {
        $lotNames = ['Parking Deck A', 'Parking Deck B', 'Underground Garage', 'North Campus', 'South Campus', 'Visitor Lot', 'Employee Lot', 'VIP Parking', 'Overflow Lot', 'Event Parking'];

        for ($l = 0; $l < $this->profile->lotCount; $l++) {
            $lot = ParkingLot::create([
                'name' => $lotNames[$l % count($lotNames)].' #'.($l + 1),
                'total_slots' => $this->profile->slotsPerLot,
                'available_slots' => $this->profile->slotsPerLot,
                'status' => 'open',
                'address' => fake()->address(),
            ]);

            $this->lots->push($lot);

            // Create slots in batches
            $slotBatch = [];
            for ($s = 1; $s <= $this->profile->slotsPerLot; $s++) {
                $floor = intdiv($s - 1, 50) + 1;
                $num = (($s - 1) % 50) + 1;
                $slotBatch[] = [
                    'id' => Str::uuid()->toString(),
                    'lot_id' => $lot->id,
                    'slot_number' => sprintf('F%d-%03d', $floor, $num),
                    'status' => 'available',
                    'slot_type' => $this->randomSlotType(),
                    'is_accessible' => $s <= ceil($this->profile->slotsPerLot * 0.05),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($slotBatch) >= 100) {
                    ParkingSlot::insert($slotBatch);
                    $slotBatch = [];
                }
            }

            if (! empty($slotBatch)) {
                ParkingSlot::insert($slotBatch);
            }
        }

        $lotIds = $this->lots->pluck('id')->toArray();
        $this->slots = ParkingSlot::whereIn('lot_id', $lotIds)->get();
    }

    private function randomSlotType(): string
    {
        $types = ['standard', 'standard', 'standard', 'standard', 'compact', 'ev_charging', 'handicap', 'premium'];

        return $types[array_rand($types)];
    }

    /**
     * Generate a realistic booking time for a given day.
     * Peak hours: 8-9am and 5-6pm. Weekdays heavier than weekends.
     */
    public function generateBookingTimes(\DateTimeInterface $day): array
    {
        $dayOfWeek = (int) $day->format('N'); // 1=Mon, 7=Sun
        $isWeekend = $dayOfWeek >= 6;

        if ($isWeekend) {
            // Weekend: lighter, spread 9am-6pm
            $startHour = rand(9, 15);
        } else {
            // Weekday: peak morning (8-9) and evening (16-17)
            $peakRoll = rand(1, 100);
            if ($peakRoll <= 30) {
                $startHour = rand(7, 8); // Morning peak
            } elseif ($peakRoll <= 50) {
                $startHour = rand(16, 17); // Evening peak
            } else {
                $startHour = rand(8, 16); // Normal hours
            }
        }

        $durationHours = rand(2, 10);
        $endHour = min($startHour + $durationHours, 23);

        $start = (clone $day)->setTime($startHour, rand(0, 59));
        $end = (clone $day)->setTime($endHour, rand(0, 59));

        return [$start, $end];
    }

    /**
     * Generate a random German-style vehicle plate.
     */
    public function generatePlate(): string
    {
        $cities = ['B', 'M', 'HH', 'K', 'F', 'S', 'D', 'DO', 'E', 'N', 'DD', 'L', 'HB', 'H', 'KA'];
        $city = $cities[array_rand($cities)];
        $letters = chr(rand(65, 90)).chr(rand(65, 90));
        $numbers = rand(100, 9999);

        return "{$city}-{$letters} {$numbers}";
    }
}
