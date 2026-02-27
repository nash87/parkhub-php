<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Production simulation: 10 German parking lots, 200 users, ~3500 bookings over 30 days.
 *
 * Run: php artisan db:seed --class=ProductionSimulationSeeder
 */
class ProductionSimulationSeeder extends Seeder
{
    // -------------------------------------------------------------------------
    // Static demo data
    // -------------------------------------------------------------------------

    private const LOTS = [
        ['name' => 'P+R Hauptbahnhof',          'address' => 'Bahnhofplatz 1, 80335 München',              'slots' => 50,  'zones' => ['Ebene A', 'Ebene B', 'Ebene C']],
        ['name' => 'Tiefgarage Marktplatz',      'address' => 'Marktplatz 5, 70173 Stuttgart',              'slots' => 80,  'zones' => ['UG1', 'UG2']],
        ['name' => 'Parkhaus Stadtmitte',         'address' => 'Rathausstraße 12, 50667 Köln',               'slots' => 60,  'zones' => ['Erdgeschoss', '1. Obergeschoss', '2. Obergeschoss']],
        ['name' => 'P+R Messegelände',           'address' => 'Messegelände Süd, 60528 Frankfurt am Main',  'slots' => 100, 'zones' => ['Halle Nord', 'Halle Süd']],
        ['name' => 'Parkplatz Einkaufszentrum',  'address' => 'Shoppingcenter 3, 22335 Hamburg',            'slots' => 40,  'zones' => ['Außenparkplatz', 'Tiefgarage']],
        ['name' => 'Tiefgarage Rathaus',         'address' => 'Rathausplatz 1, 90403 Nürnberg',             'slots' => 30,  'zones' => ['Untergeschoss']],
        ['name' => 'Parkhaus Technologiepark',   'address' => 'Technologiestraße 8, 76131 Karlsruhe',       'slots' => 75,  'zones' => ['Erdgeschoss', '1. Etage', '2. Etage']],
        ['name' => 'Parkplatz Universität',      'address' => 'Universitätsring 1, 69120 Heidelberg',       'slots' => 70,  'zones' => ['Hauptcampus', 'Nebencampus']],
        ['name' => 'Parkplatz Klinikum',         'address' => 'Klinikumsallee 15, 44137 Dortmund',          'slots' => 45,  'zones' => ['Besucher', 'Personal']],
        ['name' => 'P+R Bahnhof Ost',           'address' => 'Ostbahnhofstraße 3, 04315 Leipzig',          'slots' => 55,  'zones' => ['Tagesparker', 'Dauerparker']],
    ];

    private const FIRST_NAMES = [
        'Hans', 'Peter', 'Klaus', 'Michael', 'Thomas', 'Andreas', 'Stefan', 'Christian',
        'Markus', 'Sebastian', 'Daniel', 'Tobias', 'Florian', 'Matthias', 'Martin',
        'Frank', 'Jürgen', 'Uwe', 'Carsten', 'Oliver', 'Maria', 'Anna', 'Sandra',
        'Andrea', 'Nicole', 'Stefanie', 'Christina', 'Monika', 'Petra', 'Claudia',
        'Katja', 'Sabine', 'Julia', 'Laura', 'Sarah', 'Lisa', 'Katharina', 'Melanie',
        'Susanne', 'Anja', 'Bernd', 'Wolfgang', 'Rainer', 'Dieter', 'Helmut',
        'Gerhard', 'Manfred', 'Günter', 'Werner', 'Karl', 'Heike', 'Renate',
        'Ursula', 'Brigitte', 'Ingrid', 'Elke', 'Gabi', 'Birgit', 'Karin', 'Silke',
    ];

    private const LAST_NAMES = [
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner',
        'Becker', 'Schulz', 'Hoffmann', 'Koch', 'Richter', 'Bauer', 'Klein', 'Wolf',
        'Schröder', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger', 'Hofmann',
        'Hartmann', 'Lang', 'Schmitt', 'Winter', 'Berger', 'Weiß', 'Lange', 'Schmitz',
        'Kraus', 'Mayer', 'Huber', 'Maier', 'Lehmann', 'Köhler', 'Herrmann', 'König',
        'Walter', 'Mayer', 'Fuchs', 'Kaiser', 'Peters', 'Jung', 'Hahn', 'Scholz',
    ];

    private const DEPARTMENTS = [
        'IT', 'Verwaltung', 'Vertrieb', 'Marketing', 'Finanzen',
        'Logistik', 'Produktion', 'HR', 'Einkauf', 'Recht',
    ];

    private const PLATE_PREFIXES = [
        'M', 'HH', 'B', 'K', 'F', 'S', 'N', 'DO', 'E', 'L',
        'HD', 'KA', 'MA', 'A', 'R', 'BO', 'WÜ', 'OB', 'WI', 'MÜ',
    ];

    private const CAR_MAKES = [
        ['make' => 'Volkswagen', 'models' => ['Golf', 'Passat', 'Tiguan', 'Polo', 'T-Roc']],
        ['make' => 'BMW',        'models' => ['3er', '5er', 'X5', '1er', 'X3']],
        ['make' => 'Mercedes',   'models' => ['C-Klasse', 'E-Klasse', 'A-Klasse', 'GLC', 'B-Klasse']],
        ['make' => 'Audi',       'models' => ['A4', 'A6', 'Q5', 'A3', 'Q3']],
        ['make' => 'Opel',       'models' => ['Astra', 'Corsa', 'Insignia', 'Zafira', 'Mokka']],
        ['make' => 'Ford',       'models' => ['Focus', 'Fiesta', 'Kuga', 'Puma', 'EcoSport']],
        ['make' => 'Skoda',      'models' => ['Octavia', 'Superb', 'Fabia', 'Karoq', 'Kodiaq']],
        ['make' => 'Renault',    'models' => ['Clio', 'Megane', 'Kadjar', 'Captur', 'Zoe']],
        ['make' => 'Toyota',     'models' => ['Corolla', 'Yaris', 'RAV4', 'Aygo', 'C-HR']],
        ['make' => 'Hyundai',    'models' => ['i30', 'Tucson', 'Kona', 'i20', 'Santa Fe']],
    ];

    private const COLORS = ['Schwarz', 'Weiß', 'Silber', 'Grau', 'Blau', 'Rot', 'Grün', 'Braun'];

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------

    public function run(): void
    {
        $this->command->info('🏁 ParkHub Production Simulation Seeder starting...');

        DB::statement('PRAGMA foreign_keys = OFF');

        $this->seedSettings();
        $adminIds = $this->seedAdmins();
        $lotData  = $this->seedLots();
        $userIds  = $this->seedUsers();
        $this->seedBookings($lotData, $userIds);

        DB::statement('PRAGMA foreign_keys = ON');

        $this->command->info('✅ Seed complete! Stats:');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Parking Lots',  DB::table('parking_lots')->count()],
                ['Parking Slots', DB::table('parking_slots')->count()],
                ['Users',         DB::table('users')->count()],
                ['Vehicles',      DB::table('vehicles')->count()],
                ['Bookings',      DB::table('bookings')->count()],
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    private function seedSettings(): void
    {
        $settings = [
            ['key' => 'company_name',        'value' => 'ParkHub Demo GmbH'],
            ['key' => 'company_address',     'value' => 'Musterstraße 1, 80333 München'],
            ['key' => 'company_email',       'value' => 'info@parkhub-demo.de'],
            ['key' => 'company_phone',       'value' => '+49 89 123456'],
            ['key' => 'company_vat',         'value' => 'DE123456789'],
            ['key' => 'impressum_provider',  'value' => 'ParkHub Demo GmbH'],
            ['key' => 'impressum_address',   'value' => 'Musterstraße 1, 80333 München'],
            ['key' => 'impressum_email',     'value' => 'impressum@parkhub-demo.de'],
            ['key' => 'impressum_phone',     'value' => '+49 89 123456'],
            ['key' => 'max_booking_days',    'value' => '30'],
            ['key' => 'license_plate_mode',  'value' => 'visible'],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], ['value' => $s['value'], 'updated_at' => now()]);
        }

        $this->command->line('  → Settings seeded');
    }

    // -------------------------------------------------------------------------
    // Admin users
    // -------------------------------------------------------------------------

    private function seedAdmins(): array
    {
        $admins = [
            [
                'id'         => Str::uuid(),
                'name'       => 'Administrator',
                'username'   => 'admin',
                'email'      => 'admin@parkhub-demo.de',
                'password'   => Hash::make('ParkHub2026!'),
                'role'       => 'superadmin',
                'department' => 'IT',
                'is_active'  => true,
                'created_at' => now()->subDays(35),
                'updated_at' => now()->subDays(35),
            ],
            [
                'id'         => Str::uuid(),
                'name'       => 'Parkhaus Manager',
                'username'   => 'manager',
                'email'      => 'manager@parkhub-demo.de',
                'password'   => Hash::make('Manager2026!'),
                'role'       => 'admin',
                'department' => 'Verwaltung',
                'is_active'  => true,
                'created_at' => now()->subDays(35),
                'updated_at' => now()->subDays(35),
            ],
        ];

        DB::table('users')->insertOrIgnore($admins);
        $this->command->line('  → Admin users seeded');
        return array_column($admins, 'id');
    }

    // -------------------------------------------------------------------------
    // Parking lots, zones, slots
    // -------------------------------------------------------------------------

    private function seedLots(): array
    {
        $lotData = [];

        foreach (self::LOTS as $lotDef) {
            $lotId = Str::uuid()->toString();
            DB::table('parking_lots')->insert([
                'id'               => $lotId,
                'name'             => $lotDef['name'],
                'address'          => $lotDef['address'],
                'total_slots'      => $lotDef['slots'],
                'available_slots'  => $lotDef['slots'],
                'status'           => 'open',
                'created_at'       => now()->subDays(35),
                'updated_at'       => now(),
            ]);

            // Zones
            $zoneIds = [];
            foreach ($lotDef['zones'] as $zoneName) {
                $zoneId = Str::uuid()->toString();
                DB::table('zones')->insert([
                    'id'          => $zoneId,
                    'lot_id'      => $lotId,
                    'name'        => $zoneName,
                    'color'       => $this->zoneColor(),
                    'description' => null,
                    'created_at'  => now()->subDays(35),
                    'updated_at'  => now()->subDays(35),
                ]);
                $zoneIds[] = $zoneId;
            }

            // Slots — distributed across zones
            $slotIds    = [];
            $zoneCount  = count($zoneIds);
            $slotsPerZone = (int) ceil($lotDef['slots'] / $zoneCount);

            for ($i = 1; $i <= $lotDef['slots']; $i++) {
                $slotId = Str::uuid()->toString();
                $zoneIndex = (int) floor(($i - 1) / $slotsPerZone);
                $assignedZone = $zoneIds[min($zoneIndex, $zoneCount - 1)];

                DB::table('parking_slots')->insert([
                    'id'          => $slotId,
                    'lot_id'      => $lotId,
                    'slot_number' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'status'      => 'available',
                    'zone_id'     => $assignedZone,
                    'created_at'  => now()->subDays(35),
                    'updated_at'  => now()->subDays(35),
                ]);
                $slotIds[] = $slotId;
            }

            $lotData[] = [
                'id'       => $lotId,
                'name'     => $lotDef['name'],
                'slot_ids' => $slotIds,
            ];
        }

        $this->command->line('  → ' . count($lotData) . ' parking lots seeded');
        return $lotData;
    }

    // -------------------------------------------------------------------------
    // Users + vehicles
    // -------------------------------------------------------------------------

    private function seedUsers(): array
    {
        $userIds   = [];
        $userCount = 198;
        $usedNames = [];

        $this->command->line('  → Seeding ' . $userCount . ' users...');

        for ($i = 0; $i < $userCount; $i++) {
            $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $lastName  = self::LAST_NAMES[array_rand(self::LAST_NAMES)];

            // Ensure unique usernames
            $baseUser = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $firstName . '.' . $lastName));
            $username = $baseUser;
            $attempt  = 1;
            while (in_array($username, $usedNames)) {
                $username = $baseUser . $attempt++;
            }
            $usedNames[] = $username;

            $userId = Str::uuid()->toString();
            DB::table('users')->insert([
                'id'         => $userId,
                'name'       => $firstName . ' ' . $lastName,
                'username'   => $username,
                'email'      => $username . '@example.de',
                'password'   => Hash::make('Demo2026!'),
                'role'       => 'user',
                'department' => self::DEPARTMENTS[array_rand(self::DEPARTMENTS)],
                'is_active'  => true,
                'created_at' => now()->subDays(rand(30, 365)),
                'updated_at' => now()->subDays(rand(0, 30)),
            ]);

            // 1–2 vehicles per user
            $vehicleCount = rand(1, 2);
            $plates       = [];
            for ($v = 0; $v < $vehicleCount; $v++) {
                $plate = $this->generatePlate($plates);
                $plates[] = $plate;

                $car = self::CAR_MAKES[array_rand(self::CAR_MAKES)];
                DB::table('vehicles')->insert([
                    'id'         => Str::uuid(),
                    'user_id'    => $userId,
                    'plate'      => $plate,
                    'make'       => $car['make'],
                    'model'      => $car['models'][array_rand($car['models'])],
                    'color'      => self::COLORS[array_rand(self::COLORS)],
                    'is_default' => ($v === 0),
                    'created_at' => now()->subDays(rand(0, 30)),
                    'updated_at' => now()->subDays(rand(0, 10)),
                ]);
            }

            $userIds[] = ['id' => $userId, 'plate' => $plates[0]];
        }

        $this->command->line('  → Users + vehicles seeded');
        return $userIds;
    }

    // -------------------------------------------------------------------------
    // Bookings — 30-day simulation
    // -------------------------------------------------------------------------

    private function seedBookings(array $lotData, array $userIds): void
    {
        $bookings = [];
        $now      = Carbon::now();
        $start    = $now->copy()->subDays(30)->startOfDay();

        $this->command->line('  → Generating ~3500 bookings over 30 days...');

        for ($day = 0; $day < 30; $day++) {
            $date      = $start->copy()->addDays($day);
            $dayOfWeek = $date->dayOfWeek; // 0=Sun, 6=Sat
            $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
            $target    = $isWeekend ? rand(40, 70) : rand(130, 170);

            for ($b = 0; $b < $target; $b++) {
                $user    = $userIds[array_rand($userIds)];
                $lot     = $lotData[array_rand($lotData)];
                $slotId  = $lot['slot_ids'][array_rand($lot['slot_ids'])];

                [$startTime, $endTime] = $this->bookingWindow($date, $isWeekend);

                // Past bookings: mostly completed or cancelled
                $status = 'confirmed';
                if ($endTime->isPast()) {
                    $r = rand(1, 100);
                    if ($r <= 5) {
                        $status = 'cancelled';
                    } elseif ($r <= 25) {
                        $status = 'no_show';
                    } else {
                        $status = 'completed';
                    }
                }

                $bookings[] = [
                    'id'            => Str::uuid()->toString(),
                    'user_id'       => $user['id'],
                    'lot_id'        => $lot['id'],
                    'slot_id'       => $slotId,
                    'lot_name'      => $lot['name'],
                    'slot_number'   => DB::table('parking_slots')->where('id', $slotId)->value('slot_number') ?? '001',
                    'vehicle_plate' => $user['plate'],
                    'booking_type'  => 'einmalig',
                    'start_time'    => $startTime->toDateTimeString(),
                    'end_time'      => $endTime->toDateTimeString(),
                    'status'        => $status,
                    'checked_in_at' => ($status === 'completed') ? $startTime->copy()->addMinutes(rand(1, 15))->toDateTimeString() : null,
                    'created_at'    => $startTime->copy()->subHours(rand(1, 48))->toDateTimeString(),
                    'updated_at'    => $startTime->copy()->subHours(rand(0, 2))->toDateTimeString(),
                ];

                // Bulk insert every 200 rows to stay memory-efficient
                if (count($bookings) >= 200) {
                    DB::table('bookings')->insert($bookings);
                    $bookings = [];
                }
            }
        }

        if (!empty($bookings)) {
            DB::table('bookings')->insert($bookings);
        }

        $this->command->line('  → ' . DB::table('bookings')->count() . ' bookings inserted');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function bookingWindow(Carbon $date, bool $isWeekend): array
    {
        if ($isWeekend) {
            // Weekends: spread across 09:00-17:00
            $startHour = rand(9, 15);
            $startMin  = rand(0, 3) * 15;
        } else {
            // Weekdays: peaks at 07-09 and 16-18
            $peakMorning = rand(1, 100) <= 40;
            if ($peakMorning) {
                $startHour = rand(7, 8);
                $startMin  = rand(0, 3) * 15;
            } else {
                $startHour = rand(9, 17);
                $startMin  = rand(0, 3) * 15;
            }
        }

        $durationMinutes = $this->bookingDuration($isWeekend);
        $startTime       = $date->copy()->setHour($startHour)->setMinute($startMin)->setSecond(0);
        $endTime         = $startTime->copy()->addMinutes($durationMinutes);

        return [$startTime, $endTime];
    }

    private function bookingDuration(bool $isWeekend): int
    {
        if ($isWeekend) {
            return rand(2, 6) * 60; // 2–6 hours on weekends
        }
        $r = rand(1, 100);
        if ($r <= 30) return rand(30, 90);         // short: 30–90 min (commuters)
        if ($r <= 70) return rand(120, 300);        // medium: 2–5 hours
        return rand(360, 540);                      // long: 6–9 hours (full day)
    }

    private function generatePlate(array $used): string
    {
        $attempts = 0;
        do {
            $prefix  = self::PLATE_PREFIXES[array_rand(self::PLATE_PREFIXES)];
            $letters = strtoupper(chr(rand(65, 90)) . chr(rand(65, 90)));
            $digits  = rand(100, 9999);
            $plate   = $prefix . '-' . $letters . ' ' . $digits;
            $attempts++;
        } while (in_array($plate, $used) && $attempts < 20);

        return $plate;
    }

    private function zoneColor(): string
    {
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'];
        return $colors[array_rand($colors)];
    }
}
