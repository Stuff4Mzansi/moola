<?php

namespace App;

use App\Models\AppSetting;

class CurrencySettings
{
    public const DEFAULT = 'ZAR';

    /** @var array<string, array{label: string, symbol: string, prefix: string}> */
    public const CURRENCIES = [
        'ZAR' => ['label' => 'South African rand (ZAR)', 'symbol' => 'R', 'prefix' => 'R '],
        'USD' => ['label' => 'US dollar (USD)', 'symbol' => '$', 'prefix' => '$'],
        'EUR' => ['label' => 'Euro (EUR)', 'symbol' => '€', 'prefix' => '€'],
        'GBP' => ['label' => 'British pound (GBP)', 'symbol' => '£', 'prefix' => '£'],
        'AUD' => ['label' => 'Australian dollar (AUD)', 'symbol' => '$', 'prefix' => '$'],
        'CAD' => ['label' => 'Canadian dollar (CAD)', 'symbol' => '$', 'prefix' => '$'],
        'NZD' => ['label' => 'New Zealand dollar (NZD)', 'symbol' => '$', 'prefix' => '$'],
        'BWP' => ['label' => 'Botswana pula (BWP)', 'symbol' => 'P', 'prefix' => 'P '],
        'INR' => ['label' => 'Indian rupee (INR)', 'symbol' => '₹', 'prefix' => '₹'],
        'JPY' => ['label' => 'Japanese yen (JPY)', 'symbol' => '¥', 'prefix' => '¥'],
        'CHF' => ['label' => 'Swiss franc (CHF)', 'symbol' => 'CHF', 'prefix' => 'CHF '],
        'NGN' => ['label' => 'Nigerian naira (NGN)', 'symbol' => '₦', 'prefix' => '₦'],
        'KES' => ['label' => 'Kenyan shilling (KES)', 'symbol' => 'KSh', 'prefix' => 'KSh '],
    ];

    public function code(): string
    {
        $request = request();
        $code = $request->attributes->get('app.currency.code');

        if (! is_string($code)) {
            $code = AppSetting::query()->find(1)?->currency ?? self::DEFAULT;
            $request->attributes->set('app.currency.code', $code);
        }

        return $code;
    }

    public function symbol(): string
    {
        return self::CURRENCIES[$this->code()]['symbol'];
    }

    public function prefix(): string
    {
        return self::CURRENCIES[$this->code()]['prefix'];
    }

    public function format(int $cents): string
    {
        return $this->prefix().number_format($cents / 100, 2);
    }

    public function select(string $code): void
    {
        request()->attributes->set('app.currency.code', $code);
    }
}
