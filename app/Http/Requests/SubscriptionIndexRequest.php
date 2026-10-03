<?php

namespace App\Http\Requests;

use App\SubscriptionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionIndexRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', ...array_column(SubscriptionStatus::cases(), 'value')])],
            'sort' => ['nullable', Rule::in(['next_billing_date', 'monthly_cost', 'name'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'renewal_window' => ['nullable', Rule::in(['7', '30'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
