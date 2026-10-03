<?php

namespace App;

use Carbon\CarbonImmutable;

class RecurringSchedule
{
    public function __construct(private BillingFrequency $frequency, private CarbonImmutable $anchor) {}

    public function next(CarbonImmutable $from): CarbonImmutable
    {
        return $this->occurrence($this->firstIndex($from->startOfDay()));
    }

    /** @return list<CarbonImmutable> */
    public function between(CarbonImmutable $from, CarbonImmutable $until): array
    {
        if ($until->lt($from)) {
            return [];
        }
        $dates = [];
        $index = $this->firstIndex($from->startOfDay());
        while (($date = $this->occurrence($index))->lte($until->endOfDay())) {
            $dates[] = $date;
            $index++;
        }

        return $dates;
    }

    private function occurrence(int $index): CarbonImmutable
    {
        return $this->frequency === BillingFrequency::Weekly ? $this->anchor->addWeeks($index) : $this->anchor->addMonthsNoOverflow($index * $this->frequency->intervalMonths());
    }

    private function firstIndex(CarbonImmutable $from): int
    {
        if ($this->frequency === BillingFrequency::Weekly) {
            $index = max(0, (int) floor($this->anchor->diffInDays($from) / 7));
        } else {
            $months = ($from->year - $this->anchor->year) * 12 + $from->month - $this->anchor->month;
            $index = max(0, intdiv($months, $this->frequency->intervalMonths()));
        }
        while ($this->occurrence($index)->lt($from)) {
            $index++;
        }

        return $index;
    }
}
