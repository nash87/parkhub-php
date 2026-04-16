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
        $lotData = $this->seedLots();
        $userIds = $this->seedUsers();
        $this->seedBookings($lotData, $userIds);
        $this->seedAdminBookings($adminIds, $lotData);
        $this->seedAbsences($userIds);
        $this->seedAnnouncements($adminIds);
        $this->seedNotifications($userIds);
        $this->seedFavorites($userIds, $lotData);
        $this->seedGuestBookings($adminIds, $lotData);
        $this->seedAuditLog($userIds, $adminIds);

        DB::statement('PRAGMA foreign_keys = ON');

        $this->command->info('✅ Seed complete! Stats:');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Parking Lots',    DB::table('parking_lots')->count()],
                ['Parking Slots',   DB::table('parking_slots')->count()],
                ['Users',           DB::table('users')->count()],
                ['Vehicles',        DB::table('vehicles')->count()],
                ['Bookings',        DB::table('bookings')->count()],
                ['Absences',        DB::table('absences')->count()],
                ['Announcements',   DB::table('announcements')->count()],
                ['Notifications',   DB::table('notifications_custom')->count()],
                ['Favorites',       DB::table('favorites')->count()],
                ['Guest Bookings',  DB::table('guest_bookings')->count()],
                ['Audit Log',       DB::table('audit_log')->count()],
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    private function seedSettings(): void
    {
        $settings = [
            // Company info
            ['key' => 'company_name',        'value' => 'ParkHub Demo GmbH'],
            ['key' => 'company_address',     'value' => 'Musterstraße 1, 80333 München'],
            ['key' => 'company_email',       'value' => 'info@parkhub.test'],
            ['key' => 'company_phone',       'value' => '+49 89 123456'],
            ['key' => 'company_vat',         'value' => 'DE123456789'],
            ['key' => 'use_case',            'value' => 'corporate'],
            // Impressum
            ['key' => 'impressum_provider',       'value' => 'ParkHub Demo GmbH'],
            ['key' => 'impressum_provider_name',  'value' => 'ParkHub Demo GmbH'],
            ['key' => 'impressum_legal_form',     'value' => 'GmbH'],
            ['key' => 'impressum_street',         'value' => 'Musterstraße 1'],
            ['key' => 'impressum_zip_city',       'value' => '80333 München'],
            ['key' => 'impressum_country',        'value' => 'Deutschland'],
            ['key' => 'impressum_address',        'value' => 'Musterstraße 1, 80333 München'],
            ['key' => 'impressum_email',          'value' => 'impressum@parkhub.test'],
            ['key' => 'impressum_phone',          'value' => '+49 89 123456'],
            ['key' => 'impressum_register_court', 'value' => 'Amtsgericht München'],
            ['key' => 'impressum_register_number', 'value' => 'HRB 123456'],
            ['key' => 'impressum_vat_id',         'value' => 'DE123456789'],
            ['key' => 'impressum_responsible',    'value' => 'Max Mustermann'],
            // Booking rules
            ['key' => 'max_booking_days',    'value' => '30'],
            ['key' => 'max_bookings_per_day', 'value' => '3'],
            ['key' => 'license_plate_mode',  'value' => 'visible'],
            ['key' => 'allow_guest_bookings', 'value' => 'true'],
            ['key' => 'require_vehicle',     'value' => 'false'],
            ['key' => 'self_registration',   'value' => 'true'],
            ['key' => 'booking_visibility',  'value' => 'full'],
            // Auto-release
            ['key' => 'auto_release_enabled', 'value' => 'true'],
            ['key' => 'auto_release_minutes', 'value' => '30'],
            // Credits — disabled by default (optional feature)
            ['key' => 'credits_enabled',     'value' => 'false'],
            ['key' => 'credits_per_booking', 'value' => '1'],
            // Branding
            ['key' => 'brand_primary_color', 'value' => '#d97706'],
            ['key' => 'brand_secondary_color', 'value' => '#475569'],
            // GDPR
            ['key' => 'gdpr_enabled',        'value' => 'true'],
            ['key' => 'data_retention_days', 'value' => '365'],
            // System
            ['key' => 'setup_completed',     'value' => 'true'],
            ['key' => 'maintenance_mode',    'value' => 'false'],
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
                'id' => Str::uuid(),
                'name' => 'Administrator',
                'username' => 'admin',
                'email' => 'admin@parkhub.test',
                'password' => Hash::make(env('PARKHUB_ADMIN_PASSWORD', 'demo')),
                'role' => 'superadmin',
                'department' => 'IT',
                'is_active' => true,
                // Give admin a credits balance too — otherwise the dashboard
                // KPI row reads "Credits 0" and the demo looks dead on login.
                'credits_balance' => 35,
                'credits_monthly_quota' => 40,
                'credits_last_refilled' => now()->startOfMonth(),
                'created_at' => now()->subDays(35),
                'updated_at' => now()->subDays(35),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Parkhaus Manager',
                'username' => 'manager',
                'email' => 'manager@parkhub.test',
                'password' => Hash::make('Manager2026!'),
                'role' => 'admin',
                'department' => 'Verwaltung',
                'is_active' => true,
                'credits_balance' => 28,
                'credits_monthly_quota' => 40,
                'credits_last_refilled' => now()->startOfMonth(),
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
                'id' => $lotId,
                'name' => $lotDef['name'],
                'address' => $lotDef['address'],
                'total_slots' => $lotDef['slots'],
                'available_slots' => $lotDef['slots'],
                'status' => 'open',
                'created_at' => now()->subDays(35),
                'updated_at' => now(),
            ]);

            // Zones
            $zoneIds = [];
            foreach ($lotDef['zones'] as $zoneName) {
                $zoneId = Str::uuid()->toString();
                DB::table('zones')->insert([
                    'id' => $zoneId,
                    'lot_id' => $lotId,
                    'name' => $zoneName,
                    'color' => $this->zoneColor(),
                    'description' => null,
                    'created_at' => now()->subDays(35),
                    'updated_at' => now()->subDays(35),
                ]);
                $zoneIds[] = $zoneId;
            }

            // Slots — distributed across zones
            $slotIds = [];
            $zoneCount = count($zoneIds);
            $slotsPerZone = (int) ceil($lotDef['slots'] / $zoneCount);

            for ($i = 1; $i <= $lotDef['slots']; $i++) {
                $slotId = Str::uuid()->toString();
                $zoneIndex = (int) floor(($i - 1) / $slotsPerZone);
                $assignedZone = $zoneIds[min($zoneIndex, $zoneCount - 1)];

                DB::table('parking_slots')->insert([
                    'id' => $slotId,
                    'lot_id' => $lotId,
                    'slot_number' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'status' => 'available',
                    'zone_id' => $assignedZone,
                    'created_at' => now()->subDays(35),
                    'updated_at' => now()->subDays(35),
                ]);
                $slotIds[] = $slotId;
            }

            $lotData[] = [
                'id' => $lotId,
                'name' => $lotDef['name'],
                'slot_ids' => $slotIds,
            ];
        }

        $this->command->line('  → '.count($lotData).' parking lots seeded');

        return $lotData;
    }

    // -------------------------------------------------------------------------
    // Users + vehicles
    // -------------------------------------------------------------------------

    private function seedUsers(): array
    {
        $userIds = [];
        $userCount = 198;
        $usedNames = [];

        $this->command->line('  → Seeding '.$userCount.' users...');

        // Hash the shared demo password once instead of 198 times.
        // On slow CPUs (Render free tier 0.1 CPU), bcrypt cost 12 × 198 would
        // take several minutes and trip Render's port-scan deploy timeout.
        $demoPasswordHash = Hash::make('Demo2026!');

        for ($i = 0; $i < $userCount; $i++) {
            $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $lastName = self::LAST_NAMES[array_rand(self::LAST_NAMES)];

            // Ensure unique usernames
            $baseUser = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $firstName.'.'.$lastName));
            $username = $baseUser;
            $attempt = 1;
            while (in_array($username, $usedNames)) {
                $username = $baseUser.$attempt++;
            }
            $usedNames[] = $username;

            $userId = Str::uuid()->toString();
            DB::table('users')->insert([
                'id' => $userId,
                'name' => $firstName.' '.$lastName,
                'username' => $username,
                'email' => $username.'@example.de',
                'password' => $demoPasswordHash,
                'role' => 'user',
                'department' => self::DEPARTMENTS[array_rand(self::DEPARTMENTS)],
                'is_active' => true,
                'credits_balance' => rand(10, 40),
                'credits_monthly_quota' => 40,
                'credits_last_refilled' => now()->startOfMonth(),
                'created_at' => now()->subDays(rand(30, 365)),
                'updated_at' => now()->subDays(rand(0, 30)),
            ]);

            // 1–2 vehicles per user
            $vehicleCount = rand(1, 2);
            $plates = [];
            for ($v = 0; $v < $vehicleCount; $v++) {
                $plate = $this->generatePlate($plates);
                $plates[] = $plate;

                $car = self::CAR_MAKES[array_rand(self::CAR_MAKES)];
                DB::table('vehicles')->insert([
                    'id' => Str::uuid(),
                    'user_id' => $userId,
                    'plate' => $plate,
                    'make' => $car['make'],
                    'model' => $car['models'][array_rand($car['models'])],
                    'color' => self::COLORS[array_rand(self::COLORS)],
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
        $now = Carbon::now();
        $start = $now->copy()->subDays(30)->startOfDay();

        $this->command->line('  → Generating ~3500 bookings over 30 days...');

        // Pre-fetch slot_id → slot_number once instead of running a query per booking.
        // ~4500 bookings × 1 query = 4500 queries ≈ 30-60s on Render free tier.
        $slotNumbers = DB::table('parking_slots')
            ->pluck('slot_number', 'id')
            ->toArray();

        for ($day = 0; $day < 30; $day++) {
            $date = $start->copy()->addDays($day);
            $dayOfWeek = $date->dayOfWeek; // 0=Sun, 6=Sat
            $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
            $target = $isWeekend ? rand(40, 70) : rand(130, 170);

            for ($b = 0; $b < $target; $b++) {
                $user = $userIds[array_rand($userIds)];
                $lot = $lotData[array_rand($lotData)];
                $slotId = $lot['slot_ids'][array_rand($lot['slot_ids'])];

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
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user['id'],
                    'lot_id' => $lot['id'],
                    'slot_id' => $slotId,
                    'lot_name' => $lot['name'],
                    'slot_number' => $slotNumbers[$slotId] ?? '001',
                    'vehicle_plate' => $user['plate'],
                    'booking_type' => 'einmalig',
                    'start_time' => $startTime->toDateTimeString(),
                    'end_time' => $endTime->toDateTimeString(),
                    'status' => $status,
                    'checked_in_at' => ($status === 'completed') ? $startTime->copy()->addMinutes(rand(1, 15))->toDateTimeString() : null,
                    'created_at' => $startTime->copy()->subHours(rand(1, 48))->toDateTimeString(),
                    'updated_at' => $startTime->copy()->subHours(rand(0, 2))->toDateTimeString(),
                ];

                // Bulk insert every 200 rows to stay memory-efficient
                if (count($bookings) >= 200) {
                    DB::table('bookings')->insert($bookings);
                    $bookings = [];
                }
            }
        }

        if (! empty($bookings)) {
            DB::table('bookings')->insert($bookings);
        }

        $this->command->line('  → '.DB::table('bookings')->count().' bookings inserted');
    }

    // -------------------------------------------------------------------------
    // Admin bookings — seed a handful so the admin dashboard is not empty
    // on first login. Gives the demo "Guten Morgen, Administrator" screen
    // real numbers in the KPI row (active, this month, total).
    // -------------------------------------------------------------------------

    private function seedAdminBookings(array $adminIds, array $lotData): void
    {
        if (empty($adminIds) || empty($lotData)) {
            return;
        }

        $slotNumbers = DB::table('parking_slots')
            ->pluck('slot_number', 'id')
            ->toArray();

        $now = Carbon::now();
        $plates = ['B-AA-1000', 'B-AD-2000'];
        $rows = [];

        foreach ($adminIds as $i => $adminId) {
            $plate = $plates[$i] ?? 'B-AA-'.(1000 + $i);

            // Give each admin a vehicle so the booking's vehicle_plate is real.
            DB::table('vehicles')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'user_id' => $adminId,
                'plate' => $plate,
                'make' => $i === 0 ? 'Tesla' : 'BMW',
                'model' => $i === 0 ? 'Model 3' : 'i4',
                'color' => $i === 0 ? 'Weiß' : 'Schwarz',
                'is_default' => true,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(30),
            ]);

            // 18 bookings per admin spread across the last 25 days + next 5.
            // Mix of completed (past), active/confirmed (current), and future
            // so the dashboard surfaces meaningful counts in every KPI slot.
            for ($b = 0; $b < 18; $b++) {
                $offsetDays = rand(-25, 5);
                $date = $now->copy()->addDays($offsetDays);
                $isWeekend = in_array($date->dayOfWeek, [0, 6], true);
                [$startTime, $endTime] = $this->bookingWindow($date, $isWeekend);

                $status = 'confirmed';
                if ($endTime->isPast()) {
                    $r = rand(1, 100);
                    $status = $r <= 80 ? 'completed' : ($r <= 90 ? 'cancelled' : 'no_show');
                } elseif ($startTime->isPast() && $endTime->isFuture()) {
                    $status = 'active';
                }

                $lot = $lotData[array_rand($lotData)];
                $slotId = $lot['slot_ids'][array_rand($lot['slot_ids'])];

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminId,
                    'lot_id' => $lot['id'],
                    'slot_id' => $slotId,
                    'lot_name' => $lot['name'],
                    'slot_number' => $slotNumbers[$slotId] ?? '001',
                    'vehicle_plate' => $plate,
                    'booking_type' => 'einmalig',
                    'start_time' => $startTime->toDateTimeString(),
                    'end_time' => $endTime->toDateTimeString(),
                    'status' => $status,
                    'checked_in_at' => $status === 'completed' || $status === 'active'
                        ? $startTime->copy()->addMinutes(rand(1, 15))->toDateTimeString()
                        : null,
                    'created_at' => $startTime->copy()->subHours(rand(1, 48))->toDateTimeString(),
                    'updated_at' => $startTime->copy()->subHours(rand(0, 2))->toDateTimeString(),
                ];
            }
        }

        if ($rows) {
            DB::table('bookings')->insert($rows);
        }
        $this->command->line('  → '.count($rows).' admin bookings inserted');
    }

    // -------------------------------------------------------------------------
    // Absences — homeoffice, vacation, sick, training
    // -------------------------------------------------------------------------

    private function seedAbsences(array $userIds): void
    {
        $absences = [];
        $types = ['homeoffice', 'vacation', 'sick', 'training', 'other'];
        $weights = [50, 25, 10, 10, 5]; // homeoffice is most common in corporate
        $notes = [
            'homeoffice' => ['Konzentration auf Projektarbeit', 'Handwerker im Haus', 'Kind krank', null, null, null],
            'vacation' => ['Sommerurlaub', 'Skiurlaub', 'Familienbesuch', 'Brückentag', null],
            'sick' => ['Erkältung', 'Arzttermin', null, null],
            'training' => ['Schulung SAP', 'Sicherheitsunterweisung', 'Erste-Hilfe-Kurs', 'Weiterbildung Projektmanagement'],
            'other' => ['Dienstreise', 'Messe', 'Betriebsausflug', null],
        ];

        $now = Carbon::now();

        foreach ($userIds as $userData) {
            // Each user gets 5-15 absence entries over the last 60 days + next 14 days
            $count = rand(5, 15);
            for ($i = 0; $i < $count; $i++) {
                $type = $this->weightedRandom($types, $weights);
                $startDate = $now->copy()->subDays(rand(-14, 60));

                // Duration depends on type
                $duration = match ($type) {
                    'homeoffice' => 0, // single day
                    'vacation' => rand(1, 10),
                    'sick' => rand(0, 4),
                    'training' => rand(0, 2),
                    default => 0,
                };

                $endDate = $startDate->copy()->addDays($duration);
                $noteOptions = $notes[$type];

                $absences[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userData['id'],
                    'absence_type' => $type,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'note' => $noteOptions[array_rand($noteOptions)],
                    'source' => rand(1, 100) <= 80 ? 'manual' : 'import',
                    'created_at' => $startDate->copy()->subDays(rand(0, 7))->toDateTimeString(),
                    'updated_at' => $startDate->copy()->subDays(rand(0, 3))->toDateTimeString(),
                ];

                if (count($absences) >= 200) {
                    DB::table('absences')->insert($absences);
                    $absences = [];
                }
            }
        }

        if (! empty($absences)) {
            DB::table('absences')->insert($absences);
        }

        $this->command->line('  → '.DB::table('absences')->count().' absences inserted');
    }

    // -------------------------------------------------------------------------
    // Announcements
    // -------------------------------------------------------------------------

    private function seedAnnouncements(array $adminIds): void
    {
        $announcements = [
            [
                'title' => 'Wartungsarbeiten Tiefgarage Marktplatz',
                'message' => 'Am kommenden Wochenende (15.–16. März) finden Wartungsarbeiten an der Belüftungsanlage statt. Die Tiefgarage bleibt geöffnet, es kann jedoch zu kurzfristigen Sperrungen einzelner Ebenen kommen.',
                'severity' => 'warning',
                'active' => true,
                'expires_at' => now()->addDays(5)->toDateTimeString(),
            ],
            [
                'title' => 'Neue Parkplätze im Technologiepark',
                'message' => 'Ab sofort stehen 15 zusätzliche Stellplätze in der 2. Etage des Parkhaus Technologiepark zur Verfügung. Diese sind ab sofort buchbar.',
                'severity' => 'info',
                'active' => true,
                'expires_at' => now()->addDays(14)->toDateTimeString(),
            ],
            [
                'title' => 'E-Ladesäulen jetzt verfügbar',
                'message' => 'Im P+R Hauptbahnhof wurden 8 neue E-Ladesäulen installiert (Ebene A, Plätze 041–048). Laden ist während der Parkzeit kostenlos.',
                'severity' => 'success',
                'active' => true,
                'expires_at' => null,
            ],
            [
                'title' => 'Systemupdate erfolgreich abgeschlossen',
                'message' => 'Das ParkHub-System wurde auf Version 1.2.6 aktualisiert. Neue Features: Verbesserte Kalenderansicht, erweiterte Abwesenheitsverwaltung und optimierte Performance.',
                'severity' => 'info',
                'active' => true,
                'expires_at' => now()->addDays(7)->toDateTimeString(),
            ],
            [
                'title' => 'Winterreifenpflicht beachten',
                'message' => 'Bitte beachten Sie die gesetzliche Winterreifenpflicht. Fahrzeuge ohne Winterreifen können bei Kontrollen beanstandet werden.',
                'severity' => 'warning',
                'active' => false,
                'expires_at' => now()->subDays(30)->toDateTimeString(),
            ],
            [
                'title' => 'Parkgebühren-Anpassung zum 01.04.',
                'message' => 'Zum 1. April werden die monatlichen Kontingente angepasst. Details erhalten Sie per E-Mail von Ihrer Abteilungsleitung.',
                'severity' => 'info',
                'active' => true,
                'expires_at' => now()->addDays(20)->toDateTimeString(),
            ],
        ];

        $adminId = $adminIds[0] ?? null;

        foreach ($announcements as $a) {
            DB::table('announcements')->insert(array_merge($a, [
                'id' => Str::uuid()->toString(),
                'created_by' => $adminId,
                'created_at' => now()->subDays(rand(1, 30))->toDateTimeString(),
                'updated_at' => now()->subDays(rand(0, 5))->toDateTimeString(),
            ]));
        }

        $this->command->line('  → '.count($announcements).' announcements seeded');
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

    private function seedNotifications(array $userIds): void
    {
        $templates = [
            ['type' => 'booking_confirmed', 'title' => 'Buchung bestätigt', 'message' => 'Ihre Buchung für Stellplatz %s wurde bestätigt.'],
            ['type' => 'booking_cancelled', 'title' => 'Buchung storniert', 'message' => 'Ihre Buchung für Stellplatz %s wurde storniert.'],
            ['type' => 'booking_reminder', 'title' => 'Erinnerung', 'message' => 'Ihre Buchung für morgen: Stellplatz %s im %s.'],
            ['type' => 'system', 'title' => 'Systemhinweis', 'message' => 'Wartungsfenster am Wochenende geplant. Bitte planen Sie entsprechend.'],
            ['type' => 'absence_approved', 'title' => 'Abwesenheit eingetragen', 'message' => 'Ihre Homeoffice-Meldung für %s wurde erfolgreich gespeichert.'],
            ['type' => 'announcement', 'title' => 'Neue Mitteilung', 'message' => 'Es gibt eine neue Ankündigung: %s'],
            ['type' => 'welcome', 'title' => 'Willkommen bei ParkHub', 'message' => 'Herzlich willkommen! Buchen Sie Ihren ersten Parkplatz über das Dashboard.'],
        ];

        $notifications = [];
        $slotNumbers = ['A-012', 'B-005', 'C-033', '001', '042', '078'];
        $lotNames = array_column(self::LOTS, 'name');

        // Give ~30% of users some notifications (3-8 each)
        $selectedUsers = array_slice($userIds, 0, (int) (count($userIds) * 0.3));

        foreach ($selectedUsers as $userData) {
            $count = rand(3, 8);
            for ($i = 0; $i < $count; $i++) {
                $template = $templates[array_rand($templates)];
                $slot = $slotNumbers[array_rand($slotNumbers)];
                $lot = $lotNames[array_rand($lotNames)];
                $date = now()->subDays(rand(0, 14))->format('d.m.Y');

                $message = sprintf($template['message'], $slot, $lot, $date);

                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userData['id'],
                    'type' => $template['type'],
                    'title' => $template['title'],
                    'message' => $message,
                    'data' => null,
                    'read' => rand(1, 100) <= 60,
                    'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23))->toDateTimeString(),
                    'updated_at' => now()->subDays(rand(0, 10))->toDateTimeString(),
                ];

                if (count($notifications) >= 200) {
                    DB::table('notifications_custom')->insert($notifications);
                    $notifications = [];
                }
            }
        }

        if (! empty($notifications)) {
            DB::table('notifications_custom')->insert($notifications);
        }

        $this->command->line('  → '.DB::table('notifications_custom')->count().' notifications inserted');
    }

    // -------------------------------------------------------------------------
    // Favorites
    // -------------------------------------------------------------------------

    private function seedFavorites(array $userIds, array $lotData): void
    {
        $favorites = [];
        // ~25% of users have 1-3 favorite slots
        $selectedUsers = array_slice($userIds, 0, (int) (count($userIds) * 0.25));

        foreach ($selectedUsers as $userData) {
            $count = rand(1, 3);
            $usedSlots = [];
            for ($i = 0; $i < $count; $i++) {
                $lot = $lotData[array_rand($lotData)];
                $slotId = $lot['slot_ids'][array_rand($lot['slot_ids'])];

                if (in_array($slotId, $usedSlots)) {
                    continue;
                }
                $usedSlots[] = $slotId;

                $favorites[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userData['id'],
                    'slot_id' => $slotId,
                    'created_at' => now()->subDays(rand(0, 30))->toDateTimeString(),
                    'updated_at' => now()->subDays(rand(0, 10))->toDateTimeString(),
                ];
            }
        }

        if (! empty($favorites)) {
            DB::table('favorites')->insert($favorites);
        }

        $this->command->line('  → '.count($favorites).' favorites seeded');
    }

    // -------------------------------------------------------------------------
    // Guest bookings
    // -------------------------------------------------------------------------

    private function seedGuestBookings(array $adminIds, array $lotData): void
    {
        $guestNames = [
            'Dr. Werner Schulze', 'Frau Ingrid Lehmann', 'Herr Michael Krause',
            'Prof. Dr. Anna Bergmann', 'Lieferant Fischer GmbH', 'Handwerker Meier',
            'Kundenbesuch Siemens', 'Bewerberin Sarah Klein', 'Auditor TÜV Süd',
            'Berater McKinsey', 'Steuerberater Hoffmann', 'Rechtsanwalt Dr. König',
        ];

        $adminId = $adminIds[0] ?? $adminIds[1] ?? null;
        $guestBookings = [];

        for ($i = 0; $i < 25; $i++) {
            $lot = $lotData[array_rand($lotData)];
            $slotId = $lot['slot_ids'][array_rand($lot['slot_ids'])];
            $startTime = now()->subDays(rand(-7, 20))->setHour(rand(8, 14))->setMinute(0)->setSecond(0);
            $endTime = $startTime->copy()->addHours(rand(2, 6));

            $status = 'confirmed';
            if ($endTime->isPast()) {
                $status = rand(1, 100) <= 90 ? 'completed' : 'cancelled';
            }

            $guestBookings[] = [
                'id' => Str::uuid()->toString(),
                'created_by' => $adminId,
                'lot_id' => $lot['id'],
                'slot_id' => $slotId,
                'guest_name' => $guestNames[array_rand($guestNames)],
                'guest_code' => strtoupper(Str::random(8)),
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'vehicle_plate' => rand(1, 100) <= 60 ? $this->generatePlate([]) : null,
                'status' => $status,
                'created_at' => $startTime->copy()->subDays(rand(1, 5))->toDateTimeString(),
                'updated_at' => $startTime->copy()->subHours(rand(0, 12))->toDateTimeString(),
            ];
        }

        DB::table('guest_bookings')->insert($guestBookings);
        $this->command->line('  → '.count($guestBookings).' guest bookings seeded');
    }

    // -------------------------------------------------------------------------
    // Audit log — realistic admin/user actions
    // -------------------------------------------------------------------------

    private function seedAuditLog(array $userIds, array $adminIds): void
    {
        $actions = [
            ['action' => 'login', 'details' => null],
            ['action' => 'login', 'details' => null],
            ['action' => 'login', 'details' => null],
            ['action' => 'booking_created', 'details' => ['booking_type' => 'einmalig']],
            ['action' => 'booking_created', 'details' => ['booking_type' => 'einmalig']],
            ['action' => 'booking_cancelled', 'details' => ['reason' => 'Terminänderung']],
            ['action' => 'absence_created', 'details' => ['type' => 'homeoffice']],
            ['action' => 'absence_created', 'details' => ['type' => 'vacation']],
            ['action' => 'profile_updated', 'details' => ['fields' => ['phone', 'department']]],
            ['action' => 'password_changed', 'details' => null],
            ['action' => 'settings_updated', 'details' => ['key' => 'max_booking_days']],
            ['action' => 'user_deactivated', 'details' => ['reason' => 'Austritt']],
            ['action' => 'announcement_created', 'details' => ['title' => 'Wartungsarbeiten']],
            ['action' => 'lot_updated', 'details' => ['lot' => 'P+R Hauptbahnhof']],
        ];

        $ips = ['192.168.1.10', '192.168.1.22', '10.0.0.45', '172.16.0.100', '192.168.1.50'];

        $logs = [];
        // 200 audit log entries over the last 30 days
        for ($i = 0; $i < 200; $i++) {
            $isAdmin = rand(1, 100) <= 20;
            $userId = $isAdmin
                ? ($adminIds[array_rand($adminIds)] ?? null)
                : $userIds[array_rand($userIds)]['id'];

            $template = $actions[array_rand($actions)];
            // Admin-only actions
            if (! $isAdmin && in_array($template['action'], ['settings_updated', 'user_deactivated', 'announcement_created', 'lot_updated'])) {
                $template = $actions[0]; // fallback to login
            }

            $logs[] = [
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'username' => $isAdmin ? 'admin' : 'user',
                'action' => $template['action'],
                'details' => $template['details'] ? json_encode($template['details']) : null,
                'ip_address' => $ips[array_rand($ips)],
                'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23))->toDateTimeString(),
                'updated_at' => now()->subDays(rand(0, 10))->toDateTimeString(),
            ];

            if (count($logs) >= 100) {
                DB::table('audit_log')->insert($logs);
                $logs = [];
            }
        }

        if (! empty($logs)) {
            DB::table('audit_log')->insert($logs);
        }

        $this->command->line('  → '.DB::table('audit_log')->count().' audit log entries inserted');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function weightedRandom(array $items, array $weights): mixed
    {
        $totalWeight = array_sum($weights);
        $rand = rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($items as $i => $item) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $item;
            }
        }

        return $items[0];
    }

    private function bookingWindow(Carbon $date, bool $isWeekend): array
    {
        if ($isWeekend) {
            // Weekends: spread across 09:00-17:00
            $startHour = rand(9, 15);
            $startMin = rand(0, 3) * 15;
        } else {
            // Weekdays: peaks at 07-09 and 16-18
            $peakMorning = rand(1, 100) <= 40;
            if ($peakMorning) {
                $startHour = rand(7, 8);
                $startMin = rand(0, 3) * 15;
            } else {
                $startHour = rand(9, 17);
                $startMin = rand(0, 3) * 15;
            }
        }

        $durationMinutes = $this->bookingDuration($isWeekend);
        $startTime = $date->copy()->setHour($startHour)->setMinute($startMin)->setSecond(0);
        $endTime = $startTime->copy()->addMinutes($durationMinutes);

        return [$startTime, $endTime];
    }

    private function bookingDuration(bool $isWeekend): int
    {
        if ($isWeekend) {
            return rand(2, 6) * 60; // 2–6 hours on weekends
        }
        $r = rand(1, 100);
        if ($r <= 30) {
            return rand(30, 90);
        }         // short: 30–90 min (commuters)
        if ($r <= 70) {
            return rand(120, 300);
        }        // medium: 2–5 hours

        return rand(360, 540);                      // long: 6–9 hours (full day)
    }

    private function generatePlate(array $used): string
    {
        $attempts = 0;
        do {
            $prefix = self::PLATE_PREFIXES[array_rand(self::PLATE_PREFIXES)];
            $letters = strtoupper(chr(rand(65, 90)).chr(rand(65, 90)));
            $digits = rand(100, 9999);
            $plate = $prefix.'-'.$letters.' '.$digits;
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
