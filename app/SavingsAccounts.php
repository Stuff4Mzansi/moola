<?php

namespace App;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetReserve;
use App\Models\AssetValuation;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavingsAccounts
{
    /** @var array<int, bool> */
    private static array $held = [];

    public function locked(int $userId, Closure $action): mixed
    {
        if (isset(self::$held[$userId])) {
            return $action();
        }

        return Cache::store('database')->lock('net-worth:'.$userId, 30)->block(5, function () use ($userId, $action): mixed {
            self::$held[$userId] = true;
            try {
                return DB::transaction($action);
            } finally {
                unset(self::$held[$userId]);
            }
        });
    }

    public function balance(Asset $asset, CarbonImmutable $date, ?AssetValuation $value = null): int
    {
        $value ??= $asset->valuations()->whereDate('date', '<=', $date)->orderByDesc('date')->first();
        if ($value === null) {
            return 0;
        }
        $movements = $asset->movements()->whereDate('date', '<=', $date)->where(function (Builder $query) use ($value): void {
            $query->whereDate('date', '>', $value->date)->orWhere(fn (Builder $same): Builder => $same->whereDate('date', $value->date)->where('id', '>', $value->movement_cutoff_id));
        })->sum('amount_cents');

        return max(0, $value->amount_cents + $movements);
    }

    public function sync(SavingsGoal $goal): void
    {
        $reserves = AssetReserve::withTrashed()->where('savings_goal_id', $goal->id)->get();
        if ($goal->asset_id === null) {
            foreach ($reserves->where('is_automatic', true) as $reserve) {
                if (! $reserve->trashed()) {
                    $reserve->delete();
                }
            }

            return;
        }
        $asset = Asset::query()->where('user_id', $goal->user_id)->find($goal->asset_id);
        if ($asset === null) {
            return;
        }
        $saved = $goal->opening_cents + $goal->contributions()->whereDate('date', '<=', CarbonImmutable::today())->sum('amount_cents');
        $reserve = $reserves->first(fn (AssetReserve $item): bool => $item->asset_id === $asset->id && $item->is_automatic)
            ?? $reserves->first(fn (AssetReserve $item): bool => $item->asset_id === $asset->id)
            ?? new AssetReserve;
        foreach ($reserves as $other) {
            if ($other->id !== $reserve->id && ! $other->trashed()) {
                $other->delete();
            }
        }
        $reserve->asset_id = $asset->id;
        $reserve->fill(['savings_goal_id' => $goal->id, 'name' => 'Linked goal #'.$goal->id, 'kind' => $goal->kind === 'emergency' ? 'emergency' : 'goal', 'amount_cents' => $saved, 'is_automatic' => true]);
        $reserve->deleted_at = null;
        $reserve->save();
    }

    public function assertFunded(SavingsGoal $goal): void
    {
        $asset = $goal->account;
        if ($goal->asset_id === null) {
            return;
        }
        if ($asset === null || $asset->user_id !== $goal->user_id) {
            throw ValidationException::withMessages(['asset_id' => 'Restore the linked account or choose another account.']);
        }
        $capacity = max(0, $this->balance($asset, CarbonImmutable::today()) - $asset->withdrawal_cost_cents);
        if ($asset->reserves()->sum('amount_cents') > $capacity) {
            throw ValidationException::withMessages(['amount' => 'This account does not have enough unreserved money. Check its balance, choose another account, or record genuinely new money.']);
        }
    }

    /** @param array<string, mixed>|null $previous */
    public function changed(SavingsContribution $entry, ?array $previous): void
    {
        $goal = $entry->goal;
        if ($goal === null) {
            return;
        }
        $this->locked($goal->user_id, function () use ($entry, $previous, $goal): void {
            $current = $entry->getAttributes();
            $keys = ['asset_id', 'money_origin', 'amount_cents', 'date', 'deleted_at'];
            $different = $previous === null || collect($keys)->contains(fn (string $key): bool => ($previous[$key] ?? null) != ($current[$key] ?? null));
            if ($different) {
                $this->movement($entry, $previous, -1);
                $this->movement($entry, $current, 1);
            }
            $this->sync($goal);
        });
    }

    /** @param array<string, mixed>|null $state */
    private function movement(SavingsContribution $entry, ?array $state, int $sign): void
    {
        if ($state === null || ($state['deleted_at'] ?? null) !== null || ($state['money_origin'] ?? 'existing') !== 'new' || empty($state['asset_id'])) {
            return;
        }
        $movement = new AssetMovement(['savings_contribution_id' => $entry->id, 'amount_cents' => $sign * (int) $state['amount_cents'], 'date' => $state['date']]);
        $movement->asset_id = $state['asset_id'];
        $movement->save();
    }
}
