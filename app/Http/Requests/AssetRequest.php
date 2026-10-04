<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset ? Gate::allows('update', $asset) : $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['name' => ['required', 'string', 'max:100'], 'kind' => ['required', 'in:bank,investment,property,vehicle,other'], 'institution' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500'], 'liquidity' => ['nullable', 'in:unknown,immediate,delayed,dated,sale'], 'access_days' => ['nullable', 'required_if:liquidity,delayed', 'integer', 'min:1', 'max:3650'], 'available_date' => ['nullable', 'required_if:liquidity,dated', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'], 'withdrawal_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'value_uncertain' => ['nullable', 'boolean']];
        if (! $this->route('asset') instanceof Asset) {
            $rules['amount'] = BudgetMoney::rules();
            $rules['date'] = ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'];
        }

        return $rules;
    }
}
