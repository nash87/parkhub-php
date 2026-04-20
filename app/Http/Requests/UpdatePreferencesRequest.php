<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'language' => 'sometimes|string|max:10',
            'theme' => 'sometimes|in:light,dark,system',
            'notifications_enabled' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'push' => 'sometimes|boolean',
            'show_plate_in_calendar' => 'sometimes|boolean',
            'default_lot_id' => 'sometimes|nullable|uuid',
            'locale' => 'sometimes|string|max:10',
            'timezone' => 'sometimes|string|max:64',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('push_notifications') && ! $this->has('push')) {
            $this->merge([
                'push' => $this->input('push_notifications'),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('push') || ! $this->has('push_notifications')) {
                return;
            }

            if ($this->boolean('push') !== $this->boolean('push_notifications')) {
                $validator->errors()->add(
                    'push',
                    'The push and push_notifications fields must match when both are provided.'
                );
            }
        });
    }
}
