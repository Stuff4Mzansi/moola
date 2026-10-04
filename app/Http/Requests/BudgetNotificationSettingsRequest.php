<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BudgetNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('budget'));
    }

    public function rules(): array
    {
        return ['notifications_enabled' => ['required', 'boolean'], 'notification_threshold' => ['required', 'integer', 'between:1,99'], 'reminder_days' => ['required', 'integer', 'between:0,30'], 'period_reminder_days' => ['required', 'integer', 'between:0,14']];
    }
}
