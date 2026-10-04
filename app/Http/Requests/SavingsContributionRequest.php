<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\SavingsGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavingsContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('goal') instanceof SavingsGoal && Gate::allows('update', $this->route('goal'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['contribution_id' => ['nullable', 'integer'], 'request_id' => ['required', 'uuid'], 'amount' => BudgetMoney::rules(true), 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->route('goal')->start_date->toDateString(), 'before_or_equal:today'], 'source' => ['required', 'in:goal,budget,existing'], 'transaction_id' => ['nullable', 'integer', 'required_if:source,existing'], 'notes' => ['nullable', 'string', 'max:255']];
    }
}
