<?php

namespace App\Http\Requests;

use App\BillingFrequency;
use App\CurrencySettings;
use App\Models\Subscription;
use App\SubscriptionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');

        return $subscription instanceof Subscription
            ? Gate::allows('update', $subscription)
            : Gate::allows('create', Subscription::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'currency' => ['required', Rule::in([app(CurrencySettings::class)->code()])],
            'billing_frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'next_billing_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31', ...$this->reactivationDateRules()],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'category' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array{name: string, amount_cents: int, currency: string, billing_frequency: string, next_billing_date: string, status: string, category: ?string, website: ?string, notes: ?string} */
    public function subscriptionData(): array
    {
        $data = $this->validated();
        [$whole, $fraction] = array_pad(explode('.', (string) $data['amount'], 2), 2, '');

        return [
            'name' => $data['name'],
            'amount_cents' => (int) $whole * 100 + (int) str_pad($fraction, 2, '0'),
            'currency' => $data['currency'],
            'billing_frequency' => $data['billing_frequency'],
            'next_billing_date' => $data['next_billing_date'],
            'status' => $data['status'],
            'category' => $data['category'] ?? null,
            'website' => $data['website'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** @return list<string> */
    private function reactivationDateRules(): array
    {
        $subscription = $this->route('subscription');

        if ($subscription instanceof Subscription && $subscription->status !== SubscriptionStatus::Active && $this->input('status') === SubscriptionStatus::Active->value) {
            return ['after_or_equal:today'];
        }

        return [];
    }
}
