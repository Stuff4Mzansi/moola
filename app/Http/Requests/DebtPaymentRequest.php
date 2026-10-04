<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DebtPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('debt') instanceof Debt && Gate::allows('update', $this->route('debt'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['payment_id' => ['nullable', 'integer'], 'request_id' => ['required', 'uuid'], 'amount' => BudgetMoney::rules(true), 'interest_mode' => ['nullable', 'in:automatic,manual'], 'interest' => ['required_unless:interest_mode,automatic', 'nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\\d+(?:\\.\\d{1,2})?$/', 'lte:amount'], 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->route('debt')->balance_date->toDateString(), 'before_or_equal:today'], 'charge_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:255']];
    }
}
