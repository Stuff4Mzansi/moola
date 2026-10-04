<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\SavingsGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal instanceof SavingsGoal ? Gate::allows('update', $goal) : $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'kind' => ['required', 'in:custom,emergency,holiday,deposit'], 'target' => BudgetMoney::rules(true), 'opening' => BudgetMoney::rules(), 'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'], 'target_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'], 'monthly' => BudgetMoney::rules(), 'category_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:500']];
    }
}
