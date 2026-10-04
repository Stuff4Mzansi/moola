<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use Illuminate\Foundation\Http\FormRequest;

class AssetReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reserve_id' => ['nullable', 'integer'], 'asset_id' => ['required', 'integer'], 'goal_id' => ['nullable', 'integer', 'required_if:purpose,goal'], 'purpose' => ['required', 'in:emergency,other,goal'], 'name' => ['nullable', 'string', 'max:100', 'required_if:purpose,other'], 'amount' => BudgetMoney::rules(true)];
    }
}
