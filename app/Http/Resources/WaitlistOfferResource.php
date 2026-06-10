<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WaitlistOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WaitlistOffer
 */
class WaitlistOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'slot_id' => $this->slot_id,
            'status' => $this->status,
            'expires_at' => $this->expires_at->toISOString(),
            'claimed_booking_id' => $this->claimed_booking_id,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
