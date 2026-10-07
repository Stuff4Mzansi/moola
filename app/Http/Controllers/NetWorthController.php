<?php

namespace App\Http\Controllers;

use App\BudgetMoney;
use App\Http\Requests\AssetRequest;
use App\Http\Requests\AssetValuationRequest;
use App\Http\Requests\LiabilityRequest;
use App\Http\Requests\LiabilityValuationRequest;
use App\LiquidityAnalytics;
use App\Models\Asset;
use App\Models\AssetReserve;
use App\Models\AssetValuation;
use App\Models\Liability;
use App\Models\LiabilityValuation;
use App\Models\NetWorthSnapshot;
use App\Models\SavingsGoal;
use App\NetWorthWorkspace;
use App\SavingsAccounts;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NetWorthController extends Controller
{
    public function index(Request $request, NetWorthWorkspace $workspace, LiquidityAnalytics $liquidity): View
    {
        $request->validate(['horizon' => ['nullable', 'in:30,60,90'], 'income_delay' => ['nullable', 'integer', 'min:0', 'max:60'], 'extra_debt' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'extra_reserve' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'purchase' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'purchase_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.CarbonImmutable::today()->addDays(89)->toDateString()]]);
        $data = $workspace->build($request->user());
        $scenario = array_filter($request->only(['horizon', 'income_delay', 'extra_debt', 'extra_reserve', 'purchase', 'purchase_date']), fn (mixed $value): bool => $value !== null && $value !== '');

        $forecast = $liquidity->build($request->user(), $scenario, $data);
        if (BudgetMoney::cents($scenario['purchase'] ?? '0') > 0 && isset($scenario['purchase_date']) && CarbonImmutable::parse($scenario['purchase_date'])->gt($forecast['until'])) {
            throw ValidationException::withMessages(['purchase_date' => 'Choose a purchase date within the selected forecast window.']);
        }

        return view('net-worth.index', [...$data, 'liquidity' => $forecast, 'reserveGoals' => SavingsGoal::query()->where('user_id', $request->user()->id)->whereNull('asset_id')->orderBy('name')->get(), 'deletedReserves' => AssetReserve::onlyTrashed()->where('is_automatic', false)->whereDoesntHave('goal', fn (Builder $query): Builder => $query->whereNotNull('asset_id'))->whereHas('asset', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id))->with('asset')->get()]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $this->locked(function () use ($request): void {
            $asset = new Asset($request->safe()->only(['name', 'kind', 'institution', 'notes']));
            $asset->fill($this->liquidityFields($request));
            $asset->user_id = $request->user()->id;
            $asset->save();
            $asset->valuations()->create(['movement_cutoff_id' => 0, 'amount_cents' => BudgetMoney::cents($request->input('amount')), 'date' => $request->input('date')]);
        });

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Asset added.');
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->locked(function () use ($request, $asset): void {
            $asset->refresh();
            abort_if($asset->trashed(), 422, 'This asset was removed. Restore it before editing.');
            $asset->fill($request->safe()->only(['name', 'kind', 'institution', 'notes']));
            if ($request->has('liquidity')) {
                $asset->fill($this->liquidityFields($request));
            }
            $asset->save();
        });

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Asset details saved.');
    }

    public function value(AssetValuationRequest $request, Asset $asset): RedirectResponse
    {
        $this->locked(function () use ($request, $asset): void {
            $asset->refresh();
            abort_if($asset->trashed(), 422, 'This asset was removed. Restore it before recording a value.');
            $value = $request->filled('valuation_id') ? $asset->valuations()->findOrFail($request->integer('valuation_id')) : ($asset->valuations()->withTrashed()->whereDate('date', $request->input('date'))->first() ?? $asset->valuations()->make(['date' => $request->input('date')]));
            if ($value->trashed()) {
                throw ValidationException::withMessages(['date' => 'This date has a removed valuation. Restore it from history before editing it.']);
            }
            if ($asset->valuations()->withTrashed()->whereDate('date', $request->input('date'))->when($value->exists, fn (Builder $query): Builder => $query->where('id', '!=', $value->id))->exists()) {
                throw ValidationException::withMessages(['date' => 'Another valuation exists for this date. Edit that record instead.']);
            }
            $value->fill(['movement_cutoff_id' => $request->filled('valuation_id') ? $value->movement_cutoff_id : ($asset->movements()->max('id') ?? 0), 'amount_cents' => BudgetMoney::cents($request->input('amount')), 'date' => $request->input('date'), 'notes' => $request->input('notes')])->save();
        });

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Asset value saved. Snapshots keep their original totals.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);
        $this->locked(fn (): ?bool => $asset->delete());

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Asset removed from current net worth. Snapshots were kept.')->with('undo_asset', $asset->id);
    }

    public function restoreAsset(Asset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);
        abort_unless($asset->trashed(), 404);
        $this->locked(function () use ($asset): void {
            $asset->restore();
            foreach (SavingsGoal::query()->where('asset_id', $asset->id)->get() as $goal) {
                app(SavingsAccounts::class)->sync($goal);
            }
        });

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Asset restored.');
    }

    public function storeLiability(LiabilityRequest $request): RedirectResponse
    {
        $this->locked(function () use ($request): void {
            $liability = new Liability($request->safe()->only(['name', 'kind', 'institution', 'notes']));
            $liability->user_id = $request->user()->id;
            $liability->save();
            $liability->valuations()->create(['amount_cents' => BudgetMoney::cents($request->input('amount')), 'date' => $request->input('date')]);
        });

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Liability added.');
    }

    public function updateLiability(LiabilityRequest $request, Liability $liability): RedirectResponse
    {
        $this->locked(function () use ($request, $liability): void {
            $liability->refresh();
            abort_if($liability->trashed(), 422, 'This liability was removed. Restore it before editing.');
            $liability->fill($request->safe()->only(['name', 'kind', 'institution', 'notes']));
            $liability->save();
        });

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Liability details saved.');
    }

    public function valueLiability(LiabilityValuationRequest $request, Liability $liability): RedirectResponse
    {
        $this->locked(function () use ($request, $liability): void {
            $liability->refresh();
            abort_if($liability->trashed(), 422, 'This liability was removed. Restore it before recording a value.');
            $value = $request->filled('valuation_id') ? $liability->valuations()->findOrFail($request->integer('valuation_id')) : ($liability->valuations()->withTrashed()->whereDate('date', $request->input('date'))->first() ?? $liability->valuations()->make(['date' => $request->input('date')]));
            if ($value->trashed()) {
                throw ValidationException::withMessages(['date' => 'This date has a removed valuation. Restore it from history before editing it.']);
            }
            if ($liability->valuations()->withTrashed()->whereDate('date', $request->input('date'))->when($value->exists, fn (Builder $query): Builder => $query->where('id', '!=', $value->id))->exists()) {
                throw ValidationException::withMessages(['date' => 'Another valuation exists for this date. Edit that record instead.']);
            }
            $value->fill(['amount_cents' => BudgetMoney::cents($request->input('amount')), 'date' => $request->input('date'), 'notes' => $request->input('notes')])->save();
        });

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Liability value saved. Snapshots keep their original totals.');
    }

    public function destroyLiability(Liability $liability): RedirectResponse
    {
        Gate::authorize('delete', $liability);
        $this->locked(fn (): ?bool => $liability->delete());

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Liability removed from current net worth. Snapshots were kept.')->with('undo_liability', $liability->id);
    }

    public function restoreLiability(Liability $liability): RedirectResponse
    {
        Gate::authorize('update', $liability);
        abort_unless($liability->trashed(), 404);
        $this->locked(fn (): bool => $liability->restore());

        return to_route('net-worth.index', ['tab' => 'assets'])->with('status', 'Liability restored.');
    }

    public function removeValueLiability(Liability $liability, LiabilityValuation $valuation): RedirectResponse
    {
        Gate::authorize('update', $liability);
        abort_unless($valuation->liability_id === $liability->id, 404);
        $this->locked(function () use ($liability, $valuation): void {
            abort_unless($liability->valuations()->count() > 1, 422, 'Keep at least one valuation per liability. Edit this value or remove the liability instead.');
            $valuation->delete();
        });

        return to_route('net-worth.index', ['tab' => 'history'])->with('status', 'Liability valuation removed. Current net worth now uses the latest remaining value.')->with('undo_liability_valuation', ['liability' => $liability->id, 'valuation' => $valuation->id]);
    }

    public function restoreValueLiability(Liability $liability, LiabilityValuation $valuation): RedirectResponse
    {
        Gate::authorize('update', $liability);
        abort_unless($valuation->liability_id === $liability->id && $valuation->trashed(), 404);
        $this->locked(fn (): bool => $valuation->restore());

        return to_route('net-worth.index', ['tab' => 'history'])->with('status', 'Liability valuation restored.');
    }

    public function removeValue(Asset $asset, AssetValuation $valuation): RedirectResponse
    {
        Gate::authorize('update', $asset);
        abort_unless($valuation->asset_id === $asset->id, 404);
        $this->locked(function () use ($asset, $valuation): void {
            abort_unless($asset->valuations()->count() > 1, 422, 'Keep at least one valuation per asset. Edit this value or remove the asset instead.');
            $valuation->delete();
        });

        return to_route('net-worth.index', ['tab' => 'history'])->with('status', 'Valuation removed. Current net worth now uses the latest remaining value.')->with('undo_valuation', ['asset' => $asset->id, 'valuation' => $valuation->id]);
    }

    public function restoreValue(Asset $asset, AssetValuation $valuation): RedirectResponse
    {
        Gate::authorize('update', $asset);
        abort_unless($valuation->asset_id === $asset->id && $valuation->trashed(), 404);
        $this->locked(fn (): bool => $valuation->restore());

        return to_route('net-worth.index', ['tab' => 'history'])->with('status', 'Valuation restored.');
    }

    public function snapshot(Request $request, NetWorthWorkspace $workspace): RedirectResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today']]);
        $this->locked(function () use ($request, $workspace): void {
            if (NetWorthSnapshot::withTrashed()->where('user_id', $request->user()->id)->whereDate('date', $request->input('date'))->exists()) {
                throw ValidationException::withMessages(['date' => 'A snapshot already exists for this date, including removed records. Choose another date or restore it from history.']);
            }
            $data = $workspace->balances($request->user(), CarbonImmutable::parse($request->input('date')));
            if ($data['assets']->isEmpty() && $data['debts']->isEmpty() && $data['liabilities']->isEmpty()) {
                throw ValidationException::withMessages(['date' => 'No assets, debts, or liabilities have recorded balances on or before this date. Add a record or choose a later date.']);
            }
            $snapshot = new NetWorthSnapshot(['date' => $request->input('date'), 'assets_cents' => $data['assetTotal'], 'debts_cents' => $data['debtTotal'] + $data['liabilityTotal'], 'net_worth_cents' => $data['netWorth'], 'details' => ['assets' => $data['assets']->map(fn (array $row): array => ['name' => $row['asset']->name, 'kind' => $row['asset']->kind, 'amount' => $row['amount'], 'date' => $row['value']->date->toDateString()])->all(), 'debts' => $data['debts']->map(fn (array $row): array => ['name' => $row['debt']->name, 'amount' => $row['amount']])->all(), 'liabilities' => $data['liabilities']->map(fn (array $row): array => ['name' => $row['liability']->name, 'kind' => $row['liability']->kind, 'amount' => $row['amount'], 'date' => $row['value']->date->toDateString()])->all(), 'excluded' => $data['excluded'], 'stale' => $data['stale']]]);
            $snapshot->user_id = $request->user()->id;
            $snapshot->save();
        });

        return to_route('net-worth.index', ['tab' => 'history'])->with('status', 'Net worth snapshot saved.');
    }

    public function removeSnapshot(NetWorthSnapshot $snapshot): RedirectResponse
    {
        Gate::authorize('delete', $snapshot);
        $this->locked(fn (): ?bool => $snapshot->delete());

        return to_route('net-worth.index', ['tab' => 'history'])->with('status', 'Snapshot removed.')->with('undo_snapshot', $snapshot->id);
    }

    public function restoreSnapshot(NetWorthSnapshot $snapshot): RedirectResponse
    {
        Gate::authorize('update', $snapshot);
        abort_unless($snapshot->trashed(), 404);
        $this->locked(fn (): bool => $snapshot->restore());

        return to_route('net-worth.index', ['tab' => 'history'])->with('status', 'Snapshot restored.');
    }

    /** @return array{liquidity: string, access_days: ?int, available_date: ?string, withdrawal_cost_cents: int, value_uncertain: bool} */
    private function liquidityFields(AssetRequest $request): array
    {
        $access = $request->input('liquidity') ?? 'unknown';

        return ['liquidity' => $access, 'access_days' => $access === 'delayed' ? $request->integer('access_days') : null, 'available_date' => $access === 'dated' ? $request->input('available_date') : null, 'withdrawal_cost_cents' => BudgetMoney::cents($request->input('withdrawal_cost') ?? '0'), 'value_uncertain' => $request->boolean('value_uncertain')];
    }

    private function locked(Closure $action): mixed
    {
        return Cache::store('database')->lock('net-worth:'.auth()->id(), 30)->block(5, fn (): mixed => DB::transaction($action));
    }
}
