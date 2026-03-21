<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Merge type → absence_type before validation for Rust API parity.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'absence_type' => $this->input('absence_type', $this->input('type')),
        ]);
    }

    public function rules(): array
    {
        return [
            'absence_type' => 'sometimes|in:homeoffice,vacation,sick,training,other',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
