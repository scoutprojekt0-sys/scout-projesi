<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'password' => ['sometimes', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
