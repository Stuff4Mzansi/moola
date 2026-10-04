<?php

namespace App\Http\Controllers;

use App\BudgetMoney;
use App\Http\Requests\AssetReserveRequest;
use App\Http\Requests\LiquidityPreferenceRequest;
use App\Models\Asset;
use App\Models\AssetReserve;
use App\Models\Budget;
use App\Models\LiquidityPreference;
use App\Models\SavingsGoal;
use App\SavingsAccounts;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiquidityController extends Controller
{
    public function settings(LiquidityPreferenceRequest $request): RedirectResponse
    {
        $ids = $request->input('budget_ids') ?? [];
        abort_unless(Budget::query()->where('user_id', $request->user()->id)->whereIn('id', $ids)->count() === count($ids), 404);
        $preference = LiquidityPreference::query()->firstOrNew(['user_id' => $request->user()->id]);
        $preference->user_id = $request->user()->id;
        $preference->fill(['budget_ids' => array_map('intval', $ids), 'horizon' => $request->integer('horizon'), 'buffer_cents' => BudgetMoney::cents($request->input('buffer')), 'essential_cents' => BudgetMoney::cents($request->input('essential')), 'variable_cents' => BudgetMoney::cents($request->input('variable')), 'income_first' => $request->boolean('income_first')])->save();

        return to_route('net-worth.index', ['tab' => 'liquidity'])->with('status', 'Liquidity assumptions saved.');
    }

    public function reserve(AssetReserveRequest $request): RedirectResponse
    {
        $asset = Asset::query()->where('user_id', $request->user()->id)->findOrFail($request->integer('asset_id'));
        $goal = $request->input('purpose') === 'goal' ? SavingsGoal::query()->where('user_id', $request->user()->id)->findOrFail($request->integer('goal_id')) : null;
        $this->locked(function () use ($request, $asset, $goal): void {
            $asset->refresh();
            abort_if($asset->trashed(), 422, 'Restore this asset before reserving funds.');
            $reserve = $request->filled('reserve_id') ? AssetReserve::query()->whereHas('asset', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id))->findOrFail($request->integer('reserve_id')) : new AssetReserve;
            abort_if($reserve->is_automatic || $goal?->asset_id !== null, 422, 'Linked goal reserves update automatically. Edit the goal or its contributions instead.');
            $amount = BudgetMoney::cents($request->input('amount'));
            $capacity = max(0, app(SavingsAccounts::class)->balance($asset, CarbonImmutable::today()) - $asset->withdrawal_cost_cents);
            $other = $asset->reserves()->when($reserve->exists, fn (Builder $query): Builder => $query->where('id', '!=', $reserve->id))->sum('amount_cents');
            if ($amount + $other > $capacity) {
                throw ValidationException::withMessages(['amount' => 'Protected reserves cannot exceed this asset value after estimated withdrawal costs.']);
            }
            if ($goal !== null) {
                $saved = $goal->opening_cents + $goal->contributions()->whereDate('date', '<=', CarbonImmutable::today())->sum('amount_cents');
                $assigned = AssetReserve::query()->where('savings_goal_id', $goal->id)->when($reserve->exists, fn (Builder $query): Builder => $query->where('id', '!=', $reserve->id))->whereHas('asset')->sum('amount_cents');
                if ($amount + $assigned > $saved) {
                    throw ValidationException::withMessages(['amount' => 'Goal reservations cannot exceed its recorded savings. Record the contribution in Goals first.']);
                }
            }
            $name = $goal?->name ?? ($request->input('purpose') === 'emergency' ? ($request->input('name') ?: 'Emergency reserve') : $request->input('name'));
            if ($asset->reserves()->withTrashed()->where('name', $name)->when($reserve->exists, fn (Builder $query): Builder => $query->where('id', '!=', $reserve->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'A reserve with this name already exists for the asset. Edit or restore that reserve.']);
            }
            $reserve->asset_id = $asset->id;
            $reserve->fill(['savings_goal_id' => $goal?->id, 'kind' => $goal !== null ? ($goal->kind === 'emergency' ? 'emergency' : 'goal') : $request->input('purpose'), 'name' => $name, 'amount_cents' => $amount])->save();
        });

        return to_route('net-worth.index', ['tab' => 'liquidity'])->with('status', 'Protected reserve saved. Asset values and goal progress were not changed.');
    }

    public function remove(AssetReserve $reserve): RedirectResponse
    {
        abort_unless($reserve->asset?->user_id === auth()->id(), 403);
        abort_if($reserve->is_automatic, 422, 'Unlink the account in Goals to release this automatic reserve.');
        $this->locked(fn (): ?bool => $reserve->delete());

        return to_route('net-worth.index', ['tab' => 'liquidity'])->with('status', 'Reserve released. Its money remains in the asset.')->with('undo_reserve', $reserve->id);
    }

    public function restore(AssetReserve $reserve): RedirectResponse
    {
        abort_unless($reserve->asset?->user_id === auth()->id(), 403);
        abort_unless($reserve->trashed(), 404);
        abort_if($reserve->is_automatic || $reserve->goal?->asset_id !== null, 422, 'Manage linked goal reserves from Goals.');
        $this->locked(function () use ($reserve): void {
            $asset = $reserve->asset;
            $capacity = max(0, app(SavingsAccounts::class)->balance($asset, CarbonImmutable::today()) - $asset->withdrawal_cost_cents);
            abort_if($asset->reserves()->sum('amount_cents') + $reserve->amount_cents > $capacity, 422, 'Restore would exceed the asset value. Update the value or release another reserve first.');
            if ($reserve->goal !== null) {
                $saved = $reserve->goal->opening_cents + $reserve->goal->contributions()->whereDate('date', '<=', CarbonImmutable::today())->sum('amount_cents');
                $assigned = AssetReserve::query()->where('savings_goal_id', $reserve->savings_goal_id)->whereHas('asset')->sum('amount_cents');
                abort_if($assigned + $reserve->amount_cents > $saved, 422, 'Restore would exceed recorded goal savings. Update the goal or release another reserve first.');
            }
            $reserve->restore();
        });

        return to_route('net-worth.index', ['tab' => 'liquidity'])->with('status', 'Protected reserve restored.');
    }

    private function locked(Closure $action): mixed
    {
        return Cache::store('database')->lock('net-worth:'.auth()->id(), 30)->block(5, fn (): mixed => DB::transaction($action));
    }
}
