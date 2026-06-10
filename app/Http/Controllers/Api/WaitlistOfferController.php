<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\WaitlistOfferResource;
use App\Models\WaitlistOffer;
use App\Services\NoShow\NoShowReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for waitlist slot offers created by the no-show auto-release job.
 *
 * GET  /api/v1/waitlist/offers         — list the caller's pending offers
 * POST /api/v1/waitlist/offers/{id}/claim — convert offer to booking
 */
class WaitlistOfferController extends Controller
{
    public function __construct(private readonly NoShowReleaseService $service) {}

    /**
     * GET /api/v1/waitlist/offers
     *
     * Returns all pending (not expired) offers for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $offers = WaitlistOffer::where('user_id', $request->user()->id)
            ->where('status', WaitlistOffer::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => WaitlistOfferResource::collection($offers),
        ]);
    }

    /**
     * POST /api/v1/waitlist/offers/{id}/claim
     *
     * Claim the offer within its window. Creates a confirmed booking for the
     * freed slot. Idempotent: returns 409 if already claimed by another process.
     */
    public function claim(Request $request, string $id): JsonResponse
    {
        $offer = WaitlistOffer::findOrFail($id);

        try {
            $booking = $this->service->claimOffer($offer, $request->user()->id);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'FORBIDDEN' => response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'This offer does not belong to you.'],
                ], 403),
                'OFFER_NOT_PENDING' => response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'OFFER_NOT_PENDING', 'message' => 'Offer is no longer pending.'],
                ], 409),
                'OFFER_EXPIRED' => response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'OFFER_EXPIRED', 'message' => 'Offer has expired.'],
                ], 410),
                'SLOT_NO_LONGER_AVAILABLE' => response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'SLOT_NO_LONGER_AVAILABLE', 'message' => 'The slot is no longer available.'],
                ], 409),
                default => response()->json([
                    'success' => false, 'data' => null,
                    'error' => ['code' => 'CLAIM_FAILED', 'message' => 'Failed to claim offer.'],
                ], 500),
            };
        }

        return response()->json([
            'success' => true,
            'data' => [
                'offer' => WaitlistOfferResource::make($offer->fresh()),
                'booking' => BookingResource::make($booking),
            ],
        ], 201);
    }
}
