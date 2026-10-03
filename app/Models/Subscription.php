<?php

namespace App\Models;

use App\BillingFrequency;
use App\RecurringSchedule;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'amount_cents', 'currency', 'billing_frequency', 'next_billing_date', 'status', 'category', 'website', 'notes'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'billing_frequency' => BillingFrequency::class,
            'next_billing_date' => 'immutable_date',
            'status' => SubscriptionStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function annualCostCents(): int
    {
        return $this->amount_cents * $this->billing_frequency->paymentsPerYear();
    }

    public function monthlyCostCents(): int
    {
        return (int) round($this->annualCostCents() / 12);
    }

    public function nextRenewalDate(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        if ($this->status !== SubscriptionStatus::Active) {
            return null;
        }

        $from = ($from ?? CarbonImmutable::today())->startOfDay();

        return (new RecurringSchedule($this->billing_frequency, $this->next_billing_date))->next($from);
    }

    /** @return list<CarbonImmutable> */
    public function renewalsBetween(CarbonImmutable $from, CarbonImmutable $until): array
    {
        if ($this->status !== SubscriptionStatus::Active || $until->lt($from)) {
            return [];
        }

        return (new RecurringSchedule($this->billing_frequency, $this->next_billing_date))->between($from, $until);
    }
}
