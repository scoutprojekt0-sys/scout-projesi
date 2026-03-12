<?php

namespace App\Http\Requests\Opportunity;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpportunityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'club' || $this->user()?->role === 'manager';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'type' => 'required|in:club_need,manager_need,trial,contract',
            'location' => 'nullable|string|max:255',
            'salary' => 'nullable|numeric|min:0',
            'requirements' => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for the opportunity.',
            'description.required' => 'Please provide a description.',
            'type.required' => 'Please select an opportunity type.',
            'type.in' => 'Invalid opportunity type.',
        ];
    }
}
