<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:stripe,paypal',
            'payment_token' => 'nullable|string',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a subscription plan.',
            'plan_id.exists' => 'The selected plan is invalid.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Invalid payment method. Choose stripe or paypal.',
        ];
    }
}
