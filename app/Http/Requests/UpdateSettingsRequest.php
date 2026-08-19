<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'sometimes|string|max:255',
            'use_case' => 'sometimes|in:corporate,university,residential,other',
            'self_registration' => 'sometimes|boolean',
            'license_plate_mode' => 'sometimes|in:required,optional,disabled,visible,hidden',
            'display_name_format' => 'sometimes|in:first_name,full_name,username',
            // How much of a booking owner's identity other users see in the
            // team view and the shared calendar. See App\Services\BookingVisibility.
            'booking_visibility' => 'sometimes|in:full,firstName,initials,occupied',
            'max_bookings_per_day' => 'sometimes|integer|min:0|max:50',
            'allow_guest_bookings' => 'sometimes|boolean',
            'auto_release_minutes' => 'sometimes|integer|min:0|max:480',
            'require_vehicle' => 'sometimes|boolean',
            'waitlist_enabled' => 'sometimes|boolean',
            'min_booking_duration_hours' => 'sometimes|numeric|min:0|max:24',
            'max_booking_duration_hours' => 'sometimes|numeric|min:0|max:72',
            'credits_enabled' => 'sometimes|boolean',
            'credits_per_booking' => 'sometimes|integer|min:1|max:100',
            'primary_color' => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/',
            'secondary_color' => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/',
        ];
    }

    protected function prepareForValidation(): void
    {
        $booleanKeys = ['self_registration', 'allow_guest_bookings', 'require_vehicle', 'waitlist_enabled', 'credits_enabled'];
        foreach ($booleanKeys as $bk) {
            if ($this->has($bk)) {
                $val = $this->input($bk);
                if ($val === 'true' || $val === '1') {
                    $this->merge([$bk => true]);
                } elseif ($val === 'false' || $val === '0') {
                    $this->merge([$bk => false]);
                }
            }
        }
    }
}
