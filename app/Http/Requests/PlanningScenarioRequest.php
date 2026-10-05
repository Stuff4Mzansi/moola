<?php

namespace App\Http\Requests;

use App\BudgetMoney;
use App\Models\PlanningScenario;
use App\PlanningScenarios;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlanningScenarioRequest extends FormRequest
{
    public ?PlanningScenario $selectedScenario = null;

    private bool $loadingSavedInputs = false;

    protected function prepareForValidation(): void
    {
        if (($this->isMethod('GET') || $this->routeIs('planning-scenarios.preview')) && $this->input('scenario') !== null) {
            abort_unless(is_scalar($this->input('scenario')) && ctype_digit((string) $this->input('scenario')), 404);
            $this->selectedScenario = PlanningScenario::query()->where('user_id', $this->user()->id)->findOrFail($this->input('scenario'));
        }
        $this->loadingSavedInputs = $this->isMethod('GET') && $this->selectedScenario !== null && ! $this->has('inputs');
        $kind = $this->selectedScenario?->kind ?? $this->input('kind', 'debt');
        $defaults = app(PlanningScenarios::class)->defaults(is_string($kind) ? $kind : 'debt');
        $inputs = $this->input('inputs', $this->selectedScenario?->inputs ?? []);
        if (is_array($inputs)) {
            $inputs = array_replace($defaults, $inputs);
        }
        $this->merge(['kind' => $kind, 'inputs' => $inputs]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [$this->isMethod('GET') || $this->routeIs('planning-scenarios.preview') ? 'nullable' : 'required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'kind' => ['required', 'string', Rule::in(array_keys(app(PlanningScenarios::class)->kinds()))],
            'compare' => ['nullable', 'array', 'max:3'],
            'compare.*' => ['required', 'integer', 'distinct'],
            'comparison_window' => ['nullable', Rule::in([30, 60, 90])],
        ];
        $money = BudgetMoney::rules();
        $inputRules = match ($this->input('kind')) {
            'subscriptions' => [
                'inputs' => ['required', 'array:subscription_ids,target'],
                'inputs.subscription_ids' => ['present', 'array', 'max:100'],
                'inputs.subscription_ids.*' => ['integer', 'distinct', ...($this->loadingSavedInputs ? [] : [Rule::exists('subscriptions', 'id')->where('user_id', $this->user()->id)])],
                'inputs.target' => ['nullable', ...array_values(array_diff($money, ['required']))],
            ],
            'liquidity' => [
                'inputs' => ['required', 'array:horizon,income_delay,extra_debt,extra_reserve,purchase,purchase_after_days'],
                'inputs.horizon' => ['required', 'integer', Rule::in([30, 60, 90])],
                'inputs.income_delay' => ['required', 'integer', 'min:0', 'max:60'],
                'inputs.extra_debt' => $money,
                'inputs.extra_reserve' => $money,
                'inputs.purchase' => $money,
                'inputs.purchase_after_days' => ['required', 'integer', 'min:0', 'max:89'],
            ],
            default => [
                'inputs' => ['required', 'array:extra,strategy'],
                'inputs.extra' => $money,
                'inputs.strategy' => ['required', 'string', Rule::in(['snowball', 'avalanche'])],
            ],
        };

        return [...$rules, ...$inputRules];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->input('kind') === 'liquidity' && BudgetMoney::cents($this->input('inputs.purchase')) > 0 && (int) $this->input('inputs.purchase_after_days') >= (int) $this->input('inputs.horizon')) {
                $validator->errors()->add('inputs.purchase_after_days', 'Choose a purchase day within the scenario forecast window.');
            }
        }];
    }
}
