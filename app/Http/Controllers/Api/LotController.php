<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLotRequest;
use App\Http\Resources\ParkingLotResource;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\BookingVisibility;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class LotController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $now = now();

        // Single query: count total slots per lot
        $slotCounts = ParkingSlot::selectRaw('lot_id, COUNT(*) as total')
            ->groupBy('lot_id')
            ->pluck('total', 'lot_id');

        // Single query: count currently occupied slots per lot
        $occupiedCounts = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->selectRaw('lot_id, COUNT(*) as occupied')
            ->groupBy('lot_id')
            ->pluck('occupied', 'lot_id');

        $lots = ParkingLot::all()->map(function ($lot) use ($slotCounts, $occupiedCounts) {
            $total = $slotCounts->get($lot->id, 0);
            $occupied = $occupiedCounts->get($lot->id, 0);
            $lot->available_slots = max(0, $total - $occupied);

            return $lot;
        });

        return ParkingLotResource::collection($lots);
    }

    public function store(StoreLotRequest $request)
    {
        $this->authorize('create', ParkingLot::class);

        $data = $request->only(['name', 'address', 'total_slots', 'layout', 'status', 'hourly_rate', 'daily_max', 'monthly_pass', 'currency']);
        // Default to 10 slots if not specified — ensures bookings work immediately
        $data['total_slots'] = $data['total_slots'] ?? 10;
        $lot = ParkingLot::create($data);

        // Auto-generate slots for the new lot based on total_slots
        $totalSlots = (int) $lot->total_slots;
        if ($totalSlots > 0) {
            $slots = [];
            for ($i = 1; $i <= $totalSlots; $i++) {
                $slots[] = [
                    'id' => Str::uuid()->toString(),
                    'lot_id' => $lot->id,
                    'slot_number' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'status' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            ParkingSlot::insert($slots);
        }

        return ParkingLotResource::make($lot)->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id)
    {
        $lot = ParkingLot::findOrFail($id);
        $lot->available_slots = $this->calculateAvailable($lot);

        // Auto-generate layout from slots if not set (Rust frontend requires layout)
        if (! $lot->layout) {
            $slots = $lot->slots()->get();
            $activeBookings = Booking::with('user')
                ->where('lot_id', $id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->get()
                ->keyBy('slot_id');

            // The layout answers "which slots are free right now". It does
            // not need to name who is in the others, and it certainly does
            // not need their licence plate: the plate is on the caller's
            // own booking, and on nobody else's business. Callers see their
            // own booking in full; everyone else's identity goes through
            // the same `booking_visibility` policy as the team roster.
            $viewerId = $request->user()?->id;
            $mode = BookingVisibility::mode();

            // Resolve owners through an explicit lookup rather than the
            // relation: `User` soft-deletes, so a booking's owner genuinely
            // may not resolve, and `Collection::get()` is honest about that.
            $owners = User::query()
                ->whereIn('id', $activeBookings->pluck('user_id')->unique()->all())
                ->get(['id', 'name', 'username'])
                ->keyBy('id');

            $slotConfigs = $slots->map(function ($slot) use ($activeBookings, $viewerId, $mode, $owners) {
                $booking = $activeBookings->get($slot->id);
                $isOwn = $booking !== null && $booking->user_id === $viewerId;
                $owner = $booking !== null ? $owners->get($booking->user_id) : null;

                $bookedBy = match (true) {
                    $booking === null => null,
                    $isOwn => $owner?->name,
                    default => BookingVisibility::label($owner?->name, $owner?->username, $mode, 'Occupied'),
                };

                return [
                    'id' => $slot->id,
                    'number' => $slot->slot_number,
                    'status' => $booking !== null ? 'occupied' : 'available',
                    'vehiclePlate' => $isOwn ? $booking->vehicle_plate : null,
                    'bookedBy' => $bookedBy,
                ];
            })->values()->toArray();

            // Split into rows of max 10
            $chunks = array_chunk($slotConfigs, 10);
            $rows = [];
            foreach ($chunks as $i => $chunk) {
                $rows[] = [
                    'id' => 'row-'.($i + 1),
                    'side' => $i % 2 === 0 ? 'top' : 'bottom',
                    'slots' => $chunk,
                    'label' => 'Row '.($i + 1),
                ];
            }
            $lot->layout = ['rows' => $rows, 'roadLabel' => 'Main Road'];
        }

        return ParkingLotResource::make($lot);
    }

    public function update(Request $request, string $id)
    {
        $lot = ParkingLot::findOrFail($id);
        $this->authorize('update', $lot);
        $lot->update($request->only(['name', 'address', 'total_slots', 'layout', 'status', 'hourly_rate', 'daily_max', 'monthly_pass', 'currency']));

        return ParkingLotResource::make($lot);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);
        $this->authorize('delete', $lot);
        $lot->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function slots(Request $request, string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);

        // Fetch all active bookings for this lot in a single query, keyed by slot_id
        $now = now();
        $activeBookings = Booking::where('lot_id', $id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->get()
            ->keyBy('slot_id');

        $query = $lot->slots();

        // Filter by slot_type
        if ($request->has('type')) {
            $query->where('slot_type', $request->type);
        }

        // Filter by feature (JSON contains)
        if ($request->has('feature')) {
            $query->whereJsonContains('features', $request->feature);
        }

        $slots = $query->get()->map(function ($slot) use ($activeBookings) {
            $activeBooking = $activeBookings->get($slot->id);

            $slot->current_booking = $activeBooking ? [
                'booking_id' => $activeBooking->id,
                'user_id' => $activeBooking->user_id,
                'license_plate' => $activeBooking->vehicle_plate,
                'start_time' => $activeBooking->start_time->toISOString(),
                'end_time' => $activeBooking->end_time->toISOString(),
            ] : null;

            return $slot;
        });

        return response()->json($slots);
    }

    public function occupancy(string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);
        $totalSlots = $lot->slots()->count();
        $occupied = Booking::where('lot_id', $id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->count();

        return response()->json([
            'lot_id' => $id,
            'lot_name' => $lot->name,
            'total' => $totalSlots,
            'occupied' => $occupied,
            'available' => $totalSlots - $occupied,
            'percentage' => $totalSlots > 0 ? round(($occupied / $totalSlots) * 100) : 0,
        ]);
    }

    private function calculateAvailable(ParkingLot $lot): int
    {
        $totalSlots = $lot->slots()->count();
        $occupied = Booking::where('lot_id', $lot->id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->count();

        return max(0, $totalSlots - $occupied);
    }

    /**
     * Generate QR code for a lot (local generation — no external API).
     */
    public function qrCode(Request $request, string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);
        $data = url('/').'/book?lot='.$id;

        return response()->json([
            'lot_id' => $id,
            'lot_name' => $lot->name,
            'qr_svg' => $this->generateQrSvg($data),
            'data' => $data,
        ]);
    }

    /**
     * Generate QR code for a specific slot (local generation — no external API).
     */
    public function slotQrCode(Request $request, string $lotId, string $slotId): JsonResponse
    {
        $slot = ParkingSlot::where('lot_id', $lotId)->findOrFail($slotId);
        $data = url('/').'/book?lot='.$lotId.'&slot='.$slotId;

        return response()->json([
            'lot_id' => $lotId,
            'slot_id' => $slotId,
            'slot_number' => $slot->slot_number,
            'qr_svg' => $this->generateQrSvg($data),
            'data' => $data,
        ]);
    }

    /**
     * Generate a base64-encoded SVG QR code locally.
     */
    private function generateQrSvg(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'outputBase64' => false,
        ]);

        $svg = (new QRCode($options))->render($data);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
