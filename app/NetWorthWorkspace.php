<?php

namespace App;

use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class NetWorthWorkspace
{
    /** @return array{assets: Collection, debts: Collection, assetTotal: int, debtTotal: int, netWorth: int, groups: Collection, stale: int, excluded: int, date: CarbonImmutable} */
    public function balances(User $user, CarbonImmutable $date): array
    {
        $records = Asset::query()->where('user_id', $user->id)->with(['valuations' => fn (HasMany $values): HasMany => $values->whereDate('date', '<=', $date)->orderByDesc('date')->orderByDesc('id')])->orderBy('name')->get();
        $assets = $records->map(function (Asset $asset) use ($date): ?array {
            $value = $asset->valuations->first();
            if ($value === null) {
                return null;
            }

            return ['asset' => $asset, 'value' => $value, 'amount' => app(SavingsAccounts::class)->balance($asset, $date, $value), 'stale' => $value->date->lt($date->subDays(30))];
        })->filter()->values();
        $debtRecords = Debt::query()->where('user_id', $user->id)->with(['payments' => fn (HasMany $payments): HasMany => $payments->whereDate('date', '<=', $date)])->orderBy('name')->get();
        $debts = $debtRecords->filter(fn (Debt $debt): bool => $debt->balance_date->lte($date))->map(fn (Debt $debt): array => ['debt' => $debt, 'amount' => max(0, $debt->opening_balance_cents - $debt->payments->sum(fn (DebtPayment $payment): int => $payment->amount_cents - $payment->interest_cents))])->values();
        $assetTotal = $assets->sum('amount');
        $debtTotal = $debts->sum('amount');
        $groups = $assets->groupBy(fn (array $row): string => $row['asset']->kind)->map(fn (Collection $items, string $kind): array => ['kind' => $kind, 'amount' => $items->sum('amount'), 'share' => $assetTotal > 0 ? (int) round($items->sum('amount') * 100 / $assetTotal) : 0])->sortByDesc('amount')->values();

        return ['assets' => $assets, 'debts' => $debts, 'assetTotal' => $assetTotal, 'debtTotal' => $debtTotal, 'netWorth' => $assetTotal - $debtTotal, 'groups' => $groups, 'stale' => $assets->where('stale', true)->count(), 'excluded' => $records->count() - $assets->count() + $debtRecords->count() - $debts->count(), 'date' => $date];
    }

    /** @return array{assets: Collection, debts: Collection, assetTotal: int, debtTotal: int, netWorth: int, groups: Collection, stale: int, excluded: int, date: CarbonImmutable, snapshots: Collection, latest: ?NetWorthSnapshot, change: ?int, history: Collection, deletedAssets: Collection, deletedSnapshots: Collection, deletedValues: Collection} */
    public function build(User $user, bool $includeHistory = true): array
    {
        $data = $this->balances($user, CarbonImmutable::today());
        $snapshots = NetWorthSnapshot::query()->where('user_id', $user->id)->orderBy('date')->get();
        $latest = $snapshots->last();
        $history = $includeHistory ? AssetValuation::query()->whereHas('asset', fn (Builder $query): Builder => $query->where('user_id', $user->id))->with('asset')->orderByDesc('date')->orderByDesc('id')->get() : collect();

        return [...$data, 'snapshots' => $snapshots, 'latest' => $latest, 'change' => $latest === null ? null : $data['netWorth'] - $latest->net_worth_cents, 'history' => $history, 'deletedValues' => $includeHistory ? AssetValuation::onlyTrashed()->whereHas('asset', fn (Builder $query): Builder => $query->where('user_id', $user->id))->with('asset')->orderByDesc('date')->get() : collect(), 'deletedAssets' => $includeHistory ? Asset::onlyTrashed()->where('user_id', $user->id)->orderByDesc('deleted_at')->get() : collect(), 'deletedSnapshots' => $includeHistory ? NetWorthSnapshot::onlyTrashed()->where('user_id', $user->id)->orderByDesc('date')->get() : collect()];
    }
}
