<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\Liability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LiabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $liability = $this->route('liability');

        return $liability instanceof Liability ? Gate::allows('update', $liability) : $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['name' => ['required', 'string', 'max:100'], 'kind' => ['required', 'in:credit_card,mortgage,auto_loan,student_loan,personal_loan,tax,other'], 'institution' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500']];
        if (! $this->route('liability') instanceof Liability) {
            $rules['amount'] = BudgetMoney::rules();
            $rules['date'] = ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'];
        }

        return $rules;
    }
}
