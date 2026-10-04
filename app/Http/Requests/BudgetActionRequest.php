<?php

namespace App\Http\Requests;

use App\BillingFrequency;
use App\BudgetMoney;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BudgetActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $period = $this->route('period');
        $budget = $period instanceof BudgetPeriod ? $period->budget : $this->route('budget');
        if ($budget instanceof Budget) {
            return Gate::allows(in_array($this->route('action'), ['member-save', 'settings-save'], true) ? 'share' : 'update', $budget);
        }

        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $period = $this->route('period');
        $action = $this->route('action') ?? 'create';
        $dateRules = ['required', 'date_format:Y-m-d'];
        if ($period instanceof BudgetPeriod) {
            $dateRules[] = 'after_or_equal:'.$period->start_date->toDateString();
            $dateRules[] = 'before_or_equal:'.$period->end_date->toDateString();
        }
        $rules = match ($action) {
            'create' => [
                'name' => ['required', 'string', 'max:100'],
                'scope' => ['sometimes', Rule::in(['personal', 'household'])],
                'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01'],
                'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date', 'before_or_equal:2100-12-31'],
                'expected_income' => BudgetMoney::rules(),
                'starter_categories' => ['sometimes', 'boolean'],
                'include_subscriptions' => ['sometimes', 'boolean'],
                'copy_from' => ['nullable', 'integer'],
                'repeat_cycle' => ['sometimes', Rule::in(['none', 'monthly', 'fixed_days'])],
            ],
            'period-preview', 'period-save' => [
                'name' => ['required', 'string', 'max:100'],
                'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01'],
                'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date', 'before_or_equal:2100-12-31'],
                'preview_token' => ['nullable', 'string'],
            ],
            'recurring-save' => ['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'category_id' => ['required', 'integer'], 'amount' => BudgetMoney::rules(true), 'billing_frequency' => ['required', Rule::enum(BillingFrequency::class)], 'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'], 'is_active' => ['required', 'boolean']],
            'recurring-pay' => ['id' => ['required', 'integer'], 'amount' => BudgetMoney::rules(true), 'date' => $dateRules],
            'budget-rename' => ['name' => ['required', 'string', 'max:100']],
            'group-save' => ['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(?:\.\d{1,2})?$/']],
            'category-group' => ['id' => ['required', 'integer'], 'group_id' => ['nullable', 'integer']],
            'category-save' => ['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'amount' => BudgetMoney::rules(), 'automatic' => ['sometimes', 'boolean']],
            'recurring-remove', 'group-remove', 'category-remove', 'income-remove', 'expense-remove', 'expense-restore' => ['id' => ['required', 'integer']],
            'income-save' => ['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'expected_amount' => BudgetMoney::rules(), 'received_amount' => BudgetMoney::rules(), 'expected_date' => ['nullable', ...array_slice($dateRules, 1)], 'received_date' => ['nullable', ...array_slice($dateRules, 1), Rule::requiredIf(fn (): bool => (float) $this->input('received_amount') > 0)]],
            'expense-save', 'commitment-pay' => ['recurring_charge_id' => ['nullable', 'integer'], 'id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'amount' => BudgetMoney::rules(true), 'date' => $dateRules, 'description' => ['nullable', 'string', 'max:255']],
            'transfer' => ['from_id' => ['required', 'integer'], 'to_id' => ['required', 'integer', 'different:from_id'], 'amount' => BudgetMoney::rules(true)],
            'member-save' => ['user_id' => ['required', 'integer', 'exists:users,id'], 'role' => ['required', Rule::in(['viewer', 'editor', 'remove'])]],
            'settings-save' => ['include_subscriptions' => ['required', 'boolean'], 'repeat_cycle' => ['required', Rule::in(['none', 'monthly', 'fixed_days'])]],
            default => [],
        };
        if ($period instanceof BudgetPeriod && $action !== 'period-preview') {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }
}
