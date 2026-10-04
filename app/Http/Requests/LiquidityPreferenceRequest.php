<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use Illuminate\Foundation\Http\FormRequest;

class LiquidityPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['budget_ids' => ['nullable', 'array'], 'budget_ids.*' => ['integer', 'distinct'], 'horizon' => ['required', 'in:30,60,90'], 'buffer' => BudgetMoney::rules(), 'essential' => BudgetMoney::rules(), 'variable' => BudgetMoney::rules(), 'income_first' => ['nullable', 'boolean']];
    }
}
