<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Booking;
use App\Models\Announcement;
use App\Models\Setting;

class PublicController extends Controller
{
    public function occupancy()
    {
        $lots = ParkingLot::all()->map(function ($lot) {
            $totalSlots = $lot->slots()->count();
            $occupied = Booking::where('lot_id', $lot->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->count();

            return [
                'lot_id' => $lot->id,
                'lot_name' => $lot->name,
                'total' => $totalSlots,
                'occupied' => $occupied,
                'available' => $totalSlots - $occupied,
                'percentage' => $totalSlots > 0 ? round(($occupied / $totalSlots) * 100) : 0,
            ];
        });

        return response()->json($lots);
    }

    public function display()
    {
        $lots = ParkingLot::all()->map(function ($lot) {
            $totalSlots = $lot->slots()->count();
            $occupied = Booking::where('lot_id', $lot->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->count();

            return [
                'id' => $lot->id,
                'name' => $lot->name,
                'total' => $totalSlots,
                'occupied' => $occupied,
                'available' => $totalSlots - $occupied,
            ];
        });

        $announcements = Announcement::where('active', true)->get();
        $companyName = Setting::get('company_name', 'ParkHub');

        return response()->json([
            'company_name' => $companyName,
            'lots' => $lots,
            'announcements' => $announcements,
        ]);
    }
}
