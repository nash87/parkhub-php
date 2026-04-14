<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportExportController extends Controller
{
    /**
     * POST /api/v1/admin/import/users — import users from CSV/JSON.
     */
    public function importUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|in:csv,json',
            'data' => 'required|string',
        ]);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $rows = $this->parseData($validated['format'], $validated['data']);

        foreach ($rows as $i => $row) {
            try {
                $username = trim($row['username'] ?? $row[0] ?? '');
                $email = trim($row['email'] ?? $row[1] ?? '');
                $name = trim($row['name'] ?? $row[2] ?? '');
                $role = trim($row['role'] ?? $row[3] ?? 'user');
                $password = trim($row['password'] ?? $row[4] ?? '');

                if (! $username || ! $email) {
                    $skipped++;
                    $errors[] = ['row' => $i + 1, 'field' => 'username/email', 'message' => 'Missing required fields'];

                    continue;
                }

                if (User::where('email', $email)->orWhere('username', $username)->exists()) {
                    $skipped++;
                    $errors[] = ['row' => $i + 1, 'field' => 'email', 'message' => 'User already exists'];

                    continue;
                }

                User::create([
                    'id' => (string) Str::uuid(),
                    'username' => $username,
                    'email' => $email,
                    'name' => $name ?: $username,
                    'role' => in_array($role, ['user', 'premium']) ? $role : 'user',
                    'password' => Hash::make($password ?: Str::random(16)),
                    'is_active' => true,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = ['row' => $i + 1, 'field' => '', 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/import/lots — import parking lots from CSV/JSON.
     */
    public function importLots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|in:csv,json',
            'data' => 'required|string',
        ]);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $rows = $this->parseData($validated['format'], $validated['data']);

        foreach ($rows as $i => $row) {
            try {
                $name = trim($row['name'] ?? $row[0] ?? '');
                $address = trim($row['address'] ?? $row[1] ?? '');
                $totalSlots = (int) ($row['total_slots'] ?? $row[2] ?? 0);
                $hourlyRate = (float) ($row['hourly_rate'] ?? $row[3] ?? 0);
                $dailyMax = (float) ($row['daily_max'] ?? $row[4] ?? 0);
                $currency = trim($row['currency'] ?? $row[5] ?? 'EUR');

                if (! $name || $totalSlots <= 0) {
                    $skipped++;
                    $errors[] = ['row' => $i + 1, 'field' => 'name/total_slots', 'message' => 'Missing required fields'];

                    continue;
                }

                ParkingLot::create([
                    'name' => $name,
                    'address' => $address ?: null,
                    'total_slots' => $totalSlots,
                    'available_slots' => $totalSlots,
                    'status' => 'open',
                    'hourly_rate' => $hourlyRate > 0 ? $hourlyRate : null,
                    'daily_max' => $dailyMax > 0 ? $dailyMax : null,
                    'currency' => $currency,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = ['row' => $i + 1, 'field' => '', 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/data/export/users — CSV export of users.
     */
    public function exportUsers(): StreamedResponse
    {
        $users = User::withCount('bookings')->orderBy('username')->get();

        return response()->streamDownload(function () use ($users) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Username', 'Email', 'Name', 'Role', 'Active', 'Bookings', 'Created At']);

            foreach ($users as $u) {
                fputcsv($out, [
                    $u->id,
                    $u->username,
                    $u->email,
                    $u->name,
                    $u->role,
                    $u->is_active ? 'yes' : 'no',
                    $u->bookings_count,
                    $u->created_at?->toISOString(),
                ]);
            }

            fclose($out);
        }, 'users.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * GET /api/v1/admin/data/export/lots — CSV export of parking lots.
     */
    public function exportLots(): StreamedResponse
    {
        $lots = ParkingLot::orderBy('name')->get();

        return response()->streamDownload(function () use ($lots) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Address', 'Total Slots', 'Available Slots', 'Status', 'Hourly Rate', 'Daily Max', 'Currency']);

            foreach ($lots as $lot) {
                fputcsv($out, [
                    $lot->id,
                    $lot->name,
                    $lot->address,
                    $lot->total_slots,
                    $lot->available_slots,
                    $lot->status,
                    $lot->hourly_rate,
                    $lot->daily_max,
                    $lot->currency,
                ]);
            }

            fclose($out);
        }, 'lots.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * GET /api/v1/admin/data/export/bookings — CSV export of bookings.
     */
    public function exportBookings(Request $request): StreamedResponse
    {
        $query = Booking::with(['user', 'parkingLot'])->orderByDesc('start_time');

        if ($from = $request->query('from')) {
            $query->where('start_time', '>=', $from.' 00:00:00');
        }

        if ($to = $request->query('to')) {
            $query->where('start_time', '<=', $to.' 23:59:59');
        }

        $bookings = $query->get();

        return response()->streamDownload(function () use ($bookings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'User', 'Lot', 'Slot', 'Start', 'End', 'Status', 'Total Price', 'Currency']);

            foreach ($bookings as $b) {
                fputcsv($out, [
                    $b->id,
                    $b->user?->username ?? '-',
                    $b->parkingLot?->name ?? '-',
                    $b->slot_number ?? $b->slot_id,
                    $b->start_time,
                    $b->end_time,
                    $b->status,
                    $b->total_price,
                    $b->currency ?? 'EUR',
                ]);
            }

            fclose($out);
        }, 'bookings.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Parse CSV (base64-encoded) or JSON data into rows.
     */
    private function parseData(string $format, string $data): array
    {
        if ($format === 'json') {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        // CSV is base64-encoded
        $csv = base64_decode($data, true);
        if ($csv === false) {
            return [];
        }

        $lines = array_filter(explode("\n", $csv), fn ($l) => trim($l) !== '');

        // Skip header row if first field looks like a header
        $rows = [];
        foreach ($lines as $i => $line) {
            $cols = str_getcsv($line);
            if ($i === 0 && preg_match('/^[a-zA-Z_]+$/', trim($cols[0] ?? ''))) {
                continue; // skip header
            }
            $rows[] = $cols;
        }

        return $rows;
    }
}
