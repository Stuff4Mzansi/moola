<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssetValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('asset') instanceof Asset && Gate::allows('update', $this->route('asset'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['valuation_id' => ['nullable', 'integer'], 'amount' => BudgetMoney::rules(), 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:255']];
    }
}
