<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbsenceRequest extends FormRequest
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
            'absence_type' => 'required|in:homeoffice,vacation,sick,training,other',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date',
            'note'         => 'nullable|string|max:1000',
            'source'       => 'nullable|string|max:50',
        ];
    }
}
