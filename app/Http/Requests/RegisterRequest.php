<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PasswordPolicyRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'nullable|string|min:3|max:50|unique:users|alpha_dash',
            'email' => 'required|email|max:255|unique:users',
            'password' => ['required', 'string', 'confirmed', new PasswordPolicyRule],
            'name' => 'required|string|max:255',
        ];
    }
}
