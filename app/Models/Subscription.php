<?php

namespace App\Models;

use App\BillingFrequency;
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

        return $this->occurrenceDate($this->firstOccurrenceIndex($from));
    }

    /** @return list<CarbonImmutable> */
    public function renewalsBetween(CarbonImmutable $from, CarbonImmutable $until): array
    {
        if ($this->status !== SubscriptionStatus::Active || $until->lt($from)) {
            return [];
        }

        $dates = [];
        $index = $this->firstOccurrenceIndex($from->startOfDay());

        while (($date = $this->occurrenceDate($index))->lte($until->endOfDay())) {
            $dates[] = $date;
            $index++;
        }

        return $dates;
    }

    private function occurrenceDate(int $index): CarbonImmutable
    {
        if ($this->billing_frequency === BillingFrequency::Weekly) {
            return $this->next_billing_date->addWeeks($index);
        }

        return $this->next_billing_date->addMonthsNoOverflow($index * $this->billing_frequency->intervalMonths());
    }

    private function firstOccurrenceIndex(CarbonImmutable $from): int
    {
        $anchor = $this->next_billing_date;

        if ($this->billing_frequency === BillingFrequency::Weekly) {
            $index = max(0, (int) floor($anchor->diffInDays($from) / 7));
        } else {
            $months = ($from->year - $anchor->year) * 12 + $from->month - $anchor->month;
            $index = max(0, intdiv($months, $this->billing_frequency->intervalMonths()));
        }

        while ($this->occurrenceDate($index)->lt($from)) {
            $index++;
        }

        return $index;
    }
}
