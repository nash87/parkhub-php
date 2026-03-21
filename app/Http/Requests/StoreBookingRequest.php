<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lot_id' => 'required|uuid',
            'slot_id' => 'nullable|uuid',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'booking_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'vehicle_plate' => 'nullable|string|max:20',
            'license_plate' => 'nullable|string|max:20',
        ];
    }
}
