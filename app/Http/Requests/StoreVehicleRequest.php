<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate' => 'required|string|max:20',
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ];
    }
}
