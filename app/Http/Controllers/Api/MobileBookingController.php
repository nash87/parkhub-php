<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Lot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Mobile-Optimized Booking Flow.
 *
 * Streamlined endpoints for mobile-first booking: geolocation-based lot
 * discovery, one-step quick booking, and active booking countdown.
 */
class MobileBookingController extends Controller
{
    /**
     * GET /api/v1/mobile/nearby-lots — geolocation-based lot discovery.
     */
    public function nearbyLots(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:100|max:10000',
        ]);

        $lat = (float) $request->query('lat');
        $lng = (float) $request->query('lng');
        $radius = (float) ($request->query('radius', 1000));

        $bookingCounts = $this->activeBookingCounts();

        $lots = Lot::all()->map(function ($lot) use ($lat, $lng, $bookingCounts) {
            $lotLat = $lot->latitude ?? 0;
            $lotLng = $lot->longitude ?? 0;
            $distance = $this->haversineDistance($lat, $lng, $lotLat, $lotLng);

            $totalSlots = $lot->total_slots ?? 0;
            $activeBookings = $bookingCounts->get($lot->id, 0);
            $available = max(0, $totalSlots - $activeBookings);

            return [
                'id' => (string) $lot->id,
                'name' => $lot->name,
                'address' => $lot->address,
                'lat' => $lotLat,
                'lng' => $lotLng,
                'distance_meters' => round($distance, 1),
                'total_slots' => $totalSlots,
                'available_slots' => $available,
                'occupancy_percent' => $totalSlots > 0
                    ? round(($activeBookings / $totalSlots) * 100, 1)
                    : 0,
            ];
        })
            ->filter(fn ($l) => $l['distance_meters'] <= $radius)
            ->sortBy('distance_meters')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $lots,
            'meta' => [
                'center' => ['lat' => $lat, 'lng' => $lng],
                'radius' => $radius,
                'count' => $lots->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/mobile/quick-book — list lots eligible for quick booking.
     */
    public function quickBook(Request $request): JsonResponse
    {
        $bookingCounts = $this->activeBookingCounts();

        $lots = Lot::all()->map(function ($lot) use ($bookingCounts) {
            $totalSlots = $lot->total_slots ?? 0;
            $activeBookings = $bookingCounts->get($lot->id, 0);
            $available = max(0, $totalSlots - $activeBookings);

            return [
                'id' => (string) $lot->id,
                'name' => $lot->name,
                'available_slots' => $available,
            ];
        })
            ->filter(fn ($l) => $l['available_slots'] > 0)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $lots,
        ]);
    }

    /**
     * GET /api/v1/mobile/active-booking — current active booking with countdown.
     */
    public function activeBooking(Request $request): JsonResponse
    {
        $user = $request->user();

        $booking = Booking::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->where('end_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active booking',
            ]);
        }

        $now = Carbon::now();
        $start = Carbon::parse($booking->start_time);
        $end = Carbon::parse($booking->end_time);
        $totalSeconds = (int) $start->diffInSeconds($end, true);
        $remainingSeconds = (int) max(0, $now->diffInSeconds($end, false));
        $progress = $totalSeconds > 0
            ? round((($totalSeconds - $remainingSeconds) / $totalSeconds) * 100, 1)
            : 100;

        $lot = Lot::find($booking->lot_id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $booking->id,
                'lot_name' => $lot?->name ?? 'Unknown',
                'slot_label' => $booking->slot_label ?? 'N/A',
                'start_time' => $start->toIso8601String(),
                'end_time' => $end->toIso8601String(),
                'remaining_seconds' => $remainingSeconds,
                'total_seconds' => $totalSeconds,
                'progress_percent' => $progress,
                'status' => $booking->status,
                'checked_in' => $booking->status === 'checked_in',
            ],
        ]);
    }

    /**
     * Single aggregated query: count active confirmed bookings per lot_id.
     *
     * @return Collection<string, int>
     */
    private function activeBookingCounts(): Collection
    {
        return Booking::where('status', 'confirmed')
            ->where('end_time', '>', now())
            ->selectRaw('lot_id, COUNT(*) as count')
            ->groupBy('lot_id')
            ->pluck('count', 'lot_id');
    }

    /**
     * Haversine distance between two points in meters.
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }
}
