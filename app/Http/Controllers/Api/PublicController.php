<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Booking;
use App\Models\Announcement;
use App\Models\Setting;
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
                'lot_id'     => $lot->id,
                'lot_name'   => $lot->name,
                'total'      => $totalSlots,
                'occupied'   => $occupiedCount,
                'available'  => $totalSlots - $occupiedCount,
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
                'id'        => $lot->id,
                'name'      => $lot->name,
                'total'     => $totalSlots,
                'occupied'  => $occupiedCount,
                'available' => $totalSlots - $occupiedCount,
            ];
        });

        $announcements = Announcement::where('active', true)->get();
        $companyName = Setting::get('company_name', 'ParkHub');

        return response()->json([
            'company_name'  => $companyName,
            'lots'          => $result,
            'announcements' => $announcements,
        ]);
    }
}
