<?php

namespace App\Http\Requests\Application;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;

class ApplyApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('apply', Application::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
