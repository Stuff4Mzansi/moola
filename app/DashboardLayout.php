<?php

namespace App;

use App\Models\User;

class DashboardLayout
{
    /** @return array<string, string> */
    public function widgets(User $user): array
    {
        $widgets = ['budget-trends' => 'Budget trends', 'category-trends' => 'Category spending trends', 'spending-mix' => 'Current-period spending mix', 'budgets' => 'Budgets at a glance', 'budget-pace' => 'Current-period spending pace'];
        if (config('features.debt_tracking')) {
            $widgets['debts'] = 'Debt progress';
        }
        $widgets += ['goals' => 'Savings goals', 'goal-forecast' => 'Savings goal forecast', 'net-worth' => 'Net worth and liquidity', 'subscriptions' => 'Subscription overview'];
        if ($user->isAdmin()) {
            $widgets['household'] = 'Household management';
        }

        return $widgets;
    }

    /** @return list<array{id: string, visible: bool, width: string, height: string}> */
    public function defaults(User $user): array
    {
        return array_map(fn (string $id): array => ['id' => $id, 'visible' => $id !== 'spending-mix', 'width' => 'wide', 'height' => 'auto'], array_keys($this->widgets($user)));
    }

    /** @return list<array{id: string, visible: bool, width: string, height: string}> */
    public function build(User $user): array
    {
        $defaults = collect($this->defaults($user))->keyBy('id');
        $layout = [];
        foreach (is_array($user->dashboard_layout) ? $user->dashboard_layout : [] as $widget) {
            if (! is_array($widget) || ! is_string($widget['id'] ?? null) || ! $defaults->has($widget['id'])) {
                continue;
            }
            $default = $defaults->pull($widget['id']);
            $layout[] = ['id' => $widget['id'], 'visible' => is_bool($widget['visible'] ?? null) ? $widget['visible'] : true,
                'width' => in_array($widget['width'] ?? null, ['small', 'medium', 'wide'], true) ? $widget['width'] : $default['width'],
                'height' => in_array($widget['height'] ?? null, ['auto', 'compact', 'regular', 'tall'], true) ? $widget['height'] : $default['height']];
        }

        return [...$layout, ...$defaults->values()->all()];
    }
}
