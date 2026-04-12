<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingPass;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParkingPassController extends Controller
{
    /**
     * GET /api/v1/bookings/{id}/pass — generate digital pass with QR data.
     */
    public function generate(Request $request, string $id): JsonResponse
    {
        $booking = Booking::where('user_id', $request->user()->id)
            ->with(['lot', 'slot'])
            ->findOrFail($id);

        // Find or create pass for this booking
        $pass = ParkingPass::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'user_id' => $request->user()->id,
                'verification_code' => Str::random(24),
                'status' => 'active',
            ]
        );

        // Auto-expire if booking ended
        if ($booking->status === 'completed' || $booking->status === 'cancelled') {
            $pass->update(['status' => $booking->status === 'completed' ? 'used' : 'revoked']);
        }

        $verifyUrl = url("/api/v1/pass/verify/{$pass->verification_code}");
        $qrData = 'data:image/svg+xml;base64,'.base64_encode($this->generateQrSvg($verifyUrl));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $pass->id,
                'booking_id' => $booking->id,
                'user_id' => $pass->user_id,
                'user_name' => $request->user()->name ?? $request->user()->username,
                'lot_name' => $booking->lot?->name ?? 'Unknown',
                'slot_number' => $booking->slot?->slot_number ?? $booking->slot_id ?? 'N/A',
                'valid_from' => $booking->start_time,
                'valid_until' => $booking->end_time,
                'verification_code' => $pass->verification_code,
                'qr_data' => $qrData,
                'status' => $pass->status,
                'created_at' => $pass->created_at,
            ],
        ]);
    }

    /**
     * GET /api/v1/pass/verify/{code} — public verification endpoint.
     */
    public function verify(string $code): JsonResponse
    {
        $pass = ParkingPass::where('verification_code', $code)->first();

        if (! $pass) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'INVALID_PASS', 'message' => 'Invalid verification code.'],
            ], 404);
        }

        $booking = Booking::with(['lot', 'slot'])->find($pass->booking_id);

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $pass->status === 'active',
                'status' => $pass->status,
                'lot_name' => $booking?->lot?->name,
                'slot_number' => $booking?->slot?->slot_number ?? 'N/A',
                'valid_from' => $booking?->start_time,
                'valid_until' => $booking?->end_time,
            ],
        ]);
    }

    /**
     * GET /api/v1/me/passes — list all active passes for current user.
     */
    public function myPasses(Request $request): JsonResponse
    {
        $passes = ParkingPass::where('user_id', $request->user()->id)
            ->with(['booking.lot', 'booking.slot'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ParkingPass $pass) use ($request) {
                $booking = $pass->booking;

                return [
                    'id' => $pass->id,
                    'booking_id' => $pass->booking_id,
                    'user_id' => $pass->user_id,
                    'user_name' => $request->user()->name ?? $request->user()->username,
                    'lot_name' => $booking?->lot?->name ?? 'Unknown',
                    'slot_number' => $booking?->slot?->slot_number ?? 'N/A',
                    'valid_from' => $booking?->start_time,
                    'valid_until' => $booking?->end_time,
                    'verification_code' => $pass->verification_code,
                    'qr_data' => '',
                    'status' => $pass->status,
                    'created_at' => $pass->created_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $passes]);
    }

    /**
     * Generate a real QR code SVG using chillerlan/php-qrcode.
     */
    private function generateQrSvg(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'svgViewBoxSize' => 200,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'scale' => 5,
        ]);

        return (new QRCode($options))->render($data);
    }
}
