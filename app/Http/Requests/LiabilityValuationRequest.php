<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LiabilityValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('liability') !== null && Gate::allows('update', $this->route('liability'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['amount' => BudgetMoney::rules(), 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:255']];
    }
}
