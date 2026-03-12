<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
