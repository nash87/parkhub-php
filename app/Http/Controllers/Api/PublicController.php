<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    /**
     * Return a keyed map of lot_id => occupied count using a single aggregation query.
     */
    private function occupiedCountsByLot(): array
    {
        $now = now();

        return Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->select('lot_id', DB::raw('COUNT(*) as occupied'))
            ->groupBy('lot_id')
            ->pluck('occupied', 'lot_id')
            ->all();
    }

    public function occupancy()
    {
        // withCount('slots') loads slot totals in a single query (no N+1)
        $lots = ParkingLot::withCount('slots')->get();
        $occupied = $this->occupiedCountsByLot();

        $result = $lots->map(function ($lot) use ($occupied) {
            $totalSlots = $lot->slots_count;
            $occupiedCount = $occupied[$lot->id] ?? 0;

            return [
                'lot_id' => $lot->id,
                'lot_name' => $lot->name,
                'total' => $totalSlots,
                'occupied' => $occupiedCount,
                'available' => $totalSlots - $occupiedCount,
                'percentage' => $totalSlots > 0 ? round(($occupiedCount / $totalSlots) * 100) : 0,
            ];
        });

        return response()->json($result);
    }

    public function display()
    {
        $lots = ParkingLot::withCount('slots')->get();
        $occupied = $this->occupiedCountsByLot();

        $result = $lots->map(function ($lot) use ($occupied) {
            $totalSlots = $lot->slots_count;
            $occupiedCount = $occupied[$lot->id] ?? 0;

            return [
                'id' => $lot->id,
                'name' => $lot->name,
                'total' => $totalSlots,
                'occupied' => $occupiedCount,
                'available' => $totalSlots - $occupiedCount,
            ];
        });

        $announcements = Announcement::where('active', true)->get();
        $companyName = Setting::get('company_name', 'ParkHub');

        return response()->json([
            'company_name' => $companyName,
            'lots' => $result,
            'announcements' => $announcements,
        ]);
    }

    public function vapidKey(): JsonResponse
    {
        return response()->json(['publicKey' => Setting::get('vapid_public_key', '')]);
    }

    public function branding(): JsonResponse
    {
        $s = Setting::pluck('value', 'key')->toArray();

        return response()->json([
            'company_name' => $s['company_name'] ?? 'ParkHub',
            'primary_color' => $s['primary_color'] ?? '#d97706',
            'secondary_color' => $s['secondary_color'] ?? '#475569',
            'logo_url' => $s['logo_url'] ?? null,
            'favicon_url' => null,
            'login_background_color' => '#0f172a',
            'custom_css' => null,
        ]);
    }

    public function activeAnnouncements(): JsonResponse
    {
        $announcements = Announcement::where('active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $announcements,
            'error' => null,
            'meta' => null,
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'version' => '1.3.0']);
    }

    public function updateCheck(): JsonResponse
    {
        return response()->json(['update_available' => false, 'current_version' => '1.3.0']);
    }

    public function features(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['enabled' => ['micro_animations', 'credits']], 'error' => null, 'meta' => null]);
    }

    public function homeoffice(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'pattern' => ['weekdays' => []],
            'single_days' => Absence::where('user_id', $user->id)
                ->where('absence_type', 'homeoffice')
                ->get()
                ->map(fn ($a) => ['id' => $a->id, 'date' => $a->start_date, 'reason' => $a->note]),
            'parkingSlot' => null,
        ]);
    }
}
