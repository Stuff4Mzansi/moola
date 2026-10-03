<?php

namespace App;

class BudgetMoney
{
    public static function cents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    /** @return list<string> */
    public static function rules(bool $positive = false): array
    {
        return ['required', 'numeric', $positive ? 'min:0.01' : 'min:0', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'];
    }
}
