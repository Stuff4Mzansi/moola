<?php

namespace App\Http\Requests;

use App\BudgetNotifications;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('budget'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['enabled_types' => $this->input('enabled_types', []), 'email_enabled' => $this->input('email_enabled', 0)]);
    }

    public function rules(): array
    {
        return ['email_enabled' => ['required', 'boolean'], 'enabled_types' => ['sometimes', 'array'], 'enabled_types.*' => ['string', 'distinct', Rule::in(array_keys(BudgetNotifications::TYPES))]];
    }
}
