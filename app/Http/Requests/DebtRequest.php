<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $debt = $this->route('debt');

        return $debt instanceof Debt ? Gate::allows('update', $debt) : $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'creditor' => ['nullable', 'string', 'max:100'], 'balance' => BudgetMoney::rules(), 'balance_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'], 'annual_rate' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'minimum_payment' => BudgetMoney::rules(), 'due_anchor' => ['required', 'date_format:Y-m-d', 'after_or_equal:balance_date', 'before_or_equal:2100-12-31'], 'budget_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:500']];
    }
}
