<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParkingLot
 */
class ParkingLotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'total_slots' => $this->total_slots,
            'available_slots' => $this->available_slots,
            'layout' => $this->layout,
            'status' => $this->status,
            'hourly_rate' => $this->hourly_rate !== null ? (float) $this->hourly_rate : null,
            'daily_max' => $this->daily_max !== null ? (float) $this->daily_max : null,
            'monthly_pass' => $this->monthly_pass !== null ? (float) $this->monthly_pass : null,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'slots' => ParkingSlotResource::collection($this->whenLoaded('slots')),
            'zones' => ZoneResource::collection($this->whenLoaded('zones')),
        ];
    }
}
