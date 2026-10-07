<?php

namespace App\Http\Controllers;

use App\BudgetMoney;
use App\BudgetNotifications;
use App\BudgetPeriodReview;
use App\BudgetRecurringExpenses;
use App\BudgetWorkspace;
use App\Http\Requests\BudgetActionRequest;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetNotificationPreference;
use App\Models\BudgetPeriod;
use App\Models\MailSetting;
use App\Models\SavingsGoal;
use App\Models\User;
use App\SavingsAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private BudgetWorkspace $workspace) {}

    /** @return Collection<int, Budget> */
    private function accessibleBudgets(User $user): Collection
    {
        return Budget::visibleTo($user)->with(['periods' => fn (HasMany $periods): HasMany => $periods->orderByDesc('start_date')])->orderBy('name')->get();
    }

    public function index(Request $request): View|JsonResponse
    {
        $request->validate(['period' => ['nullable', 'integer']]);
        $budgets = $this->accessibleBudgets($request->user());
        $visiblePeriods = $budgets->flatMap->periods->sortByDesc('start_date');
        $today = CarbonImmutable::today();
        $currentPeriod = $visiblePeriods->first(fn (BudgetPeriod $candidate): bool => $candidate->start_date->lte($today) && $candidate->end_date->gte($today));
        $period = $request->filled('period') ? BudgetPeriod::query()->findOrFail($request->integer('period')) : ($currentPeriod ?? $visiblePeriods->first());
        $data = ['budgets' => $budgets, 'period' => null];
        if ($period !== null) {
            Gate::authorize('view', $period->budget);
            $data = Cache::store('database')->lock('budget:'.$period->budget_id, 30)->block(5, fn (): array => DB::transaction(fn (): array => $this->viewData($request, BudgetPeriod::query()->findOrFail($period->id))));
        }

        if ($request->expectsJson()) {
            return response()->json(['html' => view('budgets.workspace', $data)->render(), 'url' => route('budgets.index', $period === null ? [] : ['period' => $period->id]), 'message' => 'Budget refreshed.']);
        }

        return view('budgets.index', $data);
    }

    /** @return array<string, mixed> */
    private function viewData(Request $request, BudgetPeriod $period): array
    {
        $data = $this->workspace->data($period);
        app(BudgetNotifications::class)->evaluate($period, $data);

        return [...$data,
            'periodReview' => app(BudgetPeriodReview::class)->build($period, $data),
            'notificationTypes' => BudgetNotifications::TYPES,
            'emailAvailable' => MailSetting::query()->where('id', 1)->where('enabled', true)->exists(),
            'emailEnabled' => BudgetNotificationPreference::query()->where('budget_id', $period->budget_id)->where('user_id', $request->user()->id)->value('email_enabled') ?? false,
            'mutedNotificationTypes' => BudgetNotificationPreference::query()->where('budget_id', $period->budget_id)->where('user_id', $request->user()->id)->first()?->muted_types ?? [],
            'budgets' => $this->accessibleBudgets($request->user()),
            'canEdit' => Gate::allows('update', $period->budget),
            'isOwner' => Gate::allows('share', $period->budget),
            'members' => $period->budget->members()->get(),
            'availableUsers' => Gate::allows('share', $period->budget) && $period->budget->scope === 'household' ? User::query()->where('id', '!=', $period->budget->user_id)->get(['id', 'name']) : collect(),
        ];
    }

    private function response(Request $request, BudgetPeriod $period, string $message = 'Saved.', ?int $undoId = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['html' => view('budgets.workspace', $this->viewData($request, BudgetPeriod::query()->findOrFail($period->id)))->render(), 'message' => $message, 'undo_id' => $undoId, 'url' => route('budgets.index', ['period' => $period->id])]);
        }

        return redirect()->route('budgets.index', ['period' => $period->id])->with('status', $message)->with('undo_id', $undoId);
    }

    public function store(BudgetActionRequest $request): JsonResponse|RedirectResponse
    {
        return DB::transaction(function () use ($request): JsonResponse|RedirectResponse {
            $data = $request->validated();
            $source = isset($data['copy_from']) ? BudgetPeriod::query()->findOrFail($data['copy_from']) : null;
            if ($source !== null) {
                Gate::authorize('update', $source->budget);
            }
            $scope = $data['scope'] ?? 'personal';
            $budget = new Budget(['name' => $data['name'], 'scope' => $scope, 'include_subscriptions' => $data['include_subscriptions'] ?? $scope === 'personal', 'repeat_cycle' => $data['repeat_cycle'] ?? 'none', 'anchor_date' => $data['start_date']]);
            $budget->user_id = $request->user()->id;
            $budget->save();
            $period = $this->createPeriod($budget, $data, $source);

            return $this->response($request, $period, 'Budget created.');
        });
    }

    public function storePeriod(BudgetActionRequest $request, Budget $budget): JsonResponse|RedirectResponse
    {
        return Cache::store('database')->lock('budget:'.$budget->id, 30)->block(5, function () use ($request, $budget): JsonResponse|RedirectResponse {
            return DB::transaction(function () use ($request, $budget): JsonResponse|RedirectResponse {
                $data = $request->validated();
                Gate::authorize('update', $budget->fresh());
                $source = isset($data['copy_from']) ? $budget->periods()->findOrFail($data['copy_from']) : null;
                $period = $this->createPeriod($budget, $data, $source);

                return $this->response($request, $period, 'New period created. Recorded payments were not copied.');
            });
        });
    }

    /** @param array<string, mixed> $data */
    private function createPeriod(Budget $budget, array $data, ?BudgetPeriod $source): BudgetPeriod
    {
        $this->checkOverlap($budget, $data['start_date'], $data['end_date']);
        $period = $budget->periods()->create(['name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date']]);
        if ($source !== null) {
            if ($source->budget_id !== $budget->id) {
                foreach ($source->budget->recurringExpenses as $expense) {
                    $budget->recurringExpenses()->create($expense->only(['name', 'category_name', 'amount_cents', 'billing_frequency', 'start_date', 'end_date', 'is_active']));
                }
            }
            $groupIds = [];
            foreach ($source->groups as $group) {
                $groupIds[$group->id] = $period->groups()->create($group->only(['name', 'percentage_basis_points']))->id;
            }
            foreach ($source->categories as $category) {
                $copy = $period->categories()->make($category->only(['name', 'allocated_cents', 'kind']));
                $copy->budget_group_id = $groupIds[$category->budget_group_id] ?? null;
                $copy->save();
            }
            foreach ($source->incomes as $income) {
                $period->incomes()->create(['name' => $income->name, 'expected_cents' => $income->expected_cents, 'received_cents' => 0]);
            }
        } else {
            $period->categories()->create(['name' => 'Subscriptions', 'kind' => 'subscriptions', 'allocated_cents' => null]);
            $period->categories()->create(['name' => 'Other', 'kind' => 'other', 'allocated_cents' => 0]);
            if ($data['starter_categories'] ?? false) {
                foreach (['Food', 'Transport', 'Home', 'Health', 'Savings'] as $name) {
                    $period->categories()->create(['name' => $name, 'allocated_cents' => 0]);
                }
            }
            if (BudgetMoney::cents($data['expected_income']) > 0) {
                $period->incomes()->create(['name' => 'Income', 'expected_cents' => BudgetMoney::cents($data['expected_income']), 'received_cents' => 0]);
            }
        }
        $this->workspace->sync($period, true);

        return $period;
    }

    private function checkOverlap(Budget $budget, string $start, string $end, ?int $except = null): void
    {
        $overlap = $budget->periods()->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->when($except !== null, fn (Builder $query): Builder => $query->where('id', '!=', $except))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'This period overlaps an existing period in this budget. Choose dates after its end or before its start.']);
        }
    }

    public function action(BudgetActionRequest $request, BudgetPeriod $period, string $action): JsonResponse|RedirectResponse
    {
        return Cache::store('database')->lock('budget:'.$period->budget_id, 30)->block(5, function () use ($request, $period, $action): JsonResponse|RedirectResponse {
            return app(SavingsAccounts::class)->locked($period->budget->user_id, function () use ($request, $period, $action): JsonResponse|RedirectResponse {
                $period = BudgetPeriod::query()->findOrFail($period->id);
                Gate::authorize(in_array($action, ['member-save', 'settings-save'], true) ? 'share' : 'update', $period->budget);
                if ($action === 'period-preview') {
                    return $this->preview($request, $period);
                }
                abort_if($request->integer('version') !== $period->version, 409, 'This budget changed in another action or session. Refresh the budget and retry; your change has not been saved.');
                $undoId = null;
                $message = 'Saved.';
                switch ($action) {
                    case 'recurring-load':
                        app(BudgetRecurringExpenses::class)->sync($period, true);
                        $message = 'Scheduled recurring occurrences loaded for this period.';
                        break;
                    case 'recurring-save':
                        $expense = $request->filled('id') ? $period->budget->recurringExpenses()->findOrFail($request->integer('id')) : $period->budget->recurringExpenses()->make();
                        abort_if(config('features.debt_tracking') && $expense->debt_id !== null, 422, 'Manage this linked schedule on the Debts page.');
                        $category = $period->categories()->findOrFail($request->integer('category_id'));
                        $expense->fill([...$request->safe()->only(['name', 'billing_frequency', 'start_date', 'end_date', 'is_active']), 'category_name' => $category->name, 'amount_cents' => BudgetMoney::cents($request->input('amount'))])->save();
                        $period->budget->periods()->where('id', '!=', $period->id)->increment('version');
                        app(BudgetRecurringExpenses::class)->sync($period, true);
                        $message = 'Recurring schedule saved for this budget and its future periods.';
                        break;
                    case 'recurring-remove':
                        $expense = $period->budget->recurringExpenses()->findOrFail($request->integer('id'));
                        abort_if(config('features.debt_tracking') && $expense->debt_id !== null, 422, 'Unlink this schedule on the Debts page.');
                        $expense->delete();
                        $period->budget->periods()->where('id', '!=', $period->id)->increment('version');
                        app(BudgetRecurringExpenses::class)->sync($period, true);
                        $message = 'Recurring schedule removed. Recorded payments were kept.';
                        break;
                    case 'recurring-pay':
                        $charge = $period->recurringCharges()->findOrFail($request->integer('id'));
                        abort_unless($charge->is_current, 422, 'This recurring occurrence was removed. Refresh the budget.');
                        abort_if($charge->transaction()->exists(), 422, 'This recurring expense is already recorded as paid.');
                        $category = $period->categories()->find($charge->budget_category_id) ?? $period->categories()->where('kind', 'other')->sole();
                        $transaction = $period->transactions()->withTrashed()->firstOrNew(['budget_recurring_charge_id' => $charge->id]);
                        $transaction->fill(['budget_category_id' => $category->id, 'amount_cents' => BudgetMoney::cents($request->input('amount')), 'date' => $request->input('date'), 'description' => $charge->name]);
                        $transaction->deleted_at = null;
                        $transaction->save();
                        $message = 'Recurring payment recorded once. Forecasts use your payment history.';
                        break;
                    case 'budget-rename':
                        $period->budget->update($request->safe()->only(['name']));
                        $period->budget->periods()->where('id', '!=', $period->id)->increment('version');
                        $message = 'Budget renamed.';
                        break;
                    case 'group-save':
                        $group = $request->filled('id') ? $period->groups()->findOrFail($request->integer('id')) : $period->groups()->make();
                        $percentage = $request->filled('percentage') ? BudgetMoney::cents($request->input('percentage')) : null;
                        $others = $period->groups()->when($group->exists, fn (Builder $query): Builder => $query->where('id', '!=', $group->id));
                        if ((clone $others)->where('name', $request->input('name'))->exists()) {
                            throw ValidationException::withMessages(['name' => 'That group already exists in this period.']);
                        }
                        if ($others->sum('percentage_basis_points') + ($percentage ?? 0) > 10000) {
                            throw ValidationException::withMessages(['percentage' => 'Combined group limits cannot exceed 100% of expected income.']);
                        }
                        $group->fill(['name' => $request->input('name'), 'percentage_basis_points' => $percentage])->save();
                        break;
                    case 'group-remove':
                        $group = $period->groups()->findOrFail($request->integer('id'));
                        $period->categories()->where('budget_group_id', $group->id)->update(['budget_group_id' => null]);
                        $group->delete();
                        $message = 'Group removed. Its categories, limits, and expenses were kept.';
                        break;
                    case 'category-group':
                        $category = $period->categories()->findOrFail($request->integer('id'));
                        $category->budget_group_id = $request->filled('group_id') ? $period->groups()->findOrFail($request->integer('group_id'))->id : null;
                        $category->save();
                        break;
                    case 'category-save':
                        $category = $request->filled('id') ? $period->categories()->findOrFail($request->integer('id')) : new BudgetCategory(['kind' => 'custom']);
                        if ($category->kind !== 'custom' && $category->exists && $request->input('name') !== $category->name) {
                            throw ValidationException::withMessages(['name' => 'Subscriptions and Other keep their names so linked payments remain easy to find.']);
                        }
                        if ($period->categories()->where('name', $request->input('name'))->when($category->exists, fn (Builder $query): Builder => $query->where('id', '!=', $category->id))->exists()) {
                            throw ValidationException::withMessages(['name' => 'That category already exists in this period.']);
                        }
                        if ($category->exists && $category->name !== $request->input('name')) {
                            $period->budget->recurringExpenses()->where('category_name', $category->name)->update(['category_name' => $request->input('name')]);
                            SavingsGoal::query()->where('budget_id', $period->budget_id)->where('category_name', $category->name)->update(['category_name' => $request->input('name')]);
                        }
                        $category->name = $request->input('name');
                        $category->allocated_cents = $category->kind === 'subscriptions' && $request->boolean('automatic') ? null : BudgetMoney::cents($request->input('amount'));
                        $period->categories()->save($category);
                        break;
                    case 'category-remove':
                        $category = $period->categories()->findOrFail($request->integer('id'));
                        abort_unless($category->kind === 'custom', 422, 'Subscriptions and Other cannot be removed.');
                        $other = $period->categories()->where('kind', 'other')->sole();
                        $period->transactions()->withTrashed()->where('budget_category_id', $category->id)->update(['budget_category_id' => $other->id]);
                        $period->recurringCharges()->where('budget_category_id', $category->id)->update(['budget_category_id' => $other->id]);
                        $period->budget->recurringExpenses()->where('category_name', $category->name)->update(['category_name' => $other->name]);
                        $category->delete();
                        $message = 'Category removed. Its transactions moved to Other.';
                        break;
                    case 'income-save':
                        $income = $request->filled('id') ? $period->incomes()->findOrFail($request->integer('id')) : $period->incomes()->make();
                        $received = BudgetMoney::cents($request->input('received_amount'));
                        $income->fill(['name' => $request->input('name'), 'expected_cents' => BudgetMoney::cents($request->input('expected_amount')), 'expected_date' => $request->input('expected_date'), 'received_cents' => $received, 'received_date' => $received > 0 ? $request->input('received_date') : null])->save();
                        break;
                    case 'income-remove':
                        $period->incomes()->findOrFail($request->integer('id'))->delete();
                        break;
                    case 'expense-save':
                        $category = $period->categories()->findOrFail($request->integer('category_id'));
                        $transaction = $request->filled('id') ? $period->transactions()->findOrFail($request->integer('id')) : $period->transactions()->make();
                        if ($request->has('recurring_charge_id')) {
                            $charge = $request->filled('recurring_charge_id') ? $period->recurringCharges()->findOrFail($request->integer('recurring_charge_id')) : null;
                            if ($charge !== null) {
                                abort_if($transaction->budget_commitment_id !== null, 422, 'Subscription payments cannot also be recurring expense payments.');
                                abort_unless($charge->is_current || $charge->transaction?->id === $transaction->id, 422, 'This recurring occurrence was removed.');
                                abort_if($charge->transaction !== null && $charge->transaction->id !== $transaction->id, 422, 'This recurring occurrence already has a recorded payment.');
                            }
                            $transaction->budget_recurring_charge_id = $charge?->id;
                        }
                        if ($transaction->budget_commitment_id !== null && $category->kind !== 'subscriptions') {
                            throw ValidationException::withMessages(['category_id' => 'A linked subscription payment stays in Subscriptions.']);
                        }
                        $transaction->fill(['budget_category_id' => $category->id, 'amount_cents' => BudgetMoney::cents($request->input('amount')), 'date' => $request->input('date'), 'description' => $request->input('description')])->save();
                        break;
                    case 'expense-remove':
                        $transaction = $period->transactions()->findOrFail($request->integer('id'));
                        $transaction->delete();
                        $undoId = $transaction->id;
                        $message = 'Expense deleted. You can undo this deletion.';
                        break;
                    case 'expense-restore':
                        $transaction = $period->transactions()->onlyTrashed()->findOrFail($request->integer('id'));
                        if ($transaction->date->lt($period->start_date) || $transaction->date->gt($period->end_date)) {
                            throw ValidationException::withMessages(['id' => 'The deleted expense falls outside the current period. Adjust the period dates before restoring it.']);
                        }
                        $transaction->restore();
                        break;
                    case 'commitment-pay':
                        $charge = $period->commitments()->findOrFail($request->integer('id'));
                        abort_unless($charge->is_current, 422, 'This scheduled charge was removed. Refresh the budget.');
                        abort_if($charge->transaction()->exists(), 422, 'This subscription charge is already recorded as paid.');
                        $transaction = $period->transactions()->withTrashed()->firstOrNew(['budget_commitment_id' => $charge->id]);
                        $transaction->fill(['budget_category_id' => $period->categories()->where('kind', 'subscriptions')->sole()->id, 'amount_cents' => BudgetMoney::cents($request->input('amount')), 'date' => $request->input('date'), 'description' => $charge->name]);
                        $transaction->deleted_at = null;
                        $transaction->save();
                        $message = 'Payment recorded once. Its expected charge is no longer reserved.';
                        break;
                    case 'transfer':
                        $from = $period->categories()->findOrFail($request->integer('from_id'));
                        $to = $period->categories()->findOrFail($request->integer('to_id'));
                        $rows = $this->workspace->data($period)['categoryRows']->keyBy(fn (array $row): int => $row['category']->id);
                        $amount = BudgetMoney::cents($request->input('amount'));
                        if ($amount > $rows[$from->id]['planned']) {
                            throw ValidationException::withMessages(['amount' => 'You cannot move more than the source category allocation.']);
                        }
                        $from->allocated_cents = $rows[$from->id]['planned'] - $amount;
                        $to->allocated_cents = $rows[$to->id]['planned'] + $amount;
                        $from->save();
                        $to->save();
                        break;
                    case 'period-save':
                        $start = $request->input('start_date');
                        $end = $request->input('end_date');
                        $datesChanged = $start !== $period->start_date->toDateString() || $end !== $period->end_date->toDateString();
                        if ($datesChanged) {
                            if (! hash_equals($this->workspace->previewToken($period, $start, $end), $request->input('preview_token', ''))) {
                                throw ValidationException::withMessages(['preview_token' => 'Preview the date changes again before saving. The budget or subscriptions may have changed.']);
                            }
                            $this->checkDateRecords($period, $start, $end);
                            $this->checkOverlap($period->budget, $start, $end, $period->id);
                        }
                        $period->fill($request->safe()->only(['name', 'start_date', 'end_date']))->save();
                        if ($datesChanged) {
                            $this->workspace->sync($period, true);
                        }
                        break;
                    case 'member-save':
                        abort_unless($period->budget->scope === 'household', 422, 'Only household budgets can be shared.');
                        abort_if($request->integer('user_id') === $period->budget->user_id, 422, 'The owner always retains control of this budget.');
                        if ($request->input('role') === 'remove') {
                            $period->budget->members()->detach($request->integer('user_id'));
                        } else {
                            $period->budget->members()->syncWithoutDetaching([$request->integer('user_id') => ['role' => $request->input('role')]]);
                        }
                        break;
                    case 'settings-save':
                        $period->budget->update($request->safe()->only(['include_subscriptions', 'repeat_cycle']));
                        foreach ($period->budget->periods()->whereDate('end_date', '>=', CarbonImmutable::today()->toDateString())->get() as $openPeriod) {
                            $this->workspace->sync($openPeriod, true);
                        }
                        $period->refresh();
                        break;
                    default:
                        abort(404);
                }
                $period->increment('version');

                return $this->response($request, $period, $message, $undoId);
            });
        });
    }

    private function checkDateRecords(BudgetPeriod $period, string $start, string $end): void
    {
        $outside = $period->transactions()->where(fn (Builder $query): Builder => $query->whereDate('date', '<', $start)->orWhereDate('date', '>', $end))->exists();
        $outsideIncome = $period->incomes()->where('received_cents', '>', 0)->where(fn (Builder $query): Builder => $query->whereDate('received_date', '<', $start)->orWhereDate('received_date', '>', $end))->exists();
        if ($outside || $outsideIncome) {
            throw ValidationException::withMessages(['start_date' => 'These dates would exclude recorded expenses or received income. Correct their dates first, or keep a period that includes them.']);
        }
    }

    private function preview(BudgetActionRequest $request, BudgetPeriod $period): JsonResponse
    {
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $this->checkDateRecords($period, $start, $end);
        $this->checkOverlap($period->budget, $start, $end, $period->id);
        $candidate = clone $period;
        $candidate->start_date = $start;
        $candidate->end_date = $end;
        $newCharges = $this->workspace->expectedCommitments($candidate);

        $newRecurring = app(BudgetRecurringExpenses::class)->expected($candidate);

        return response()->json(['old_recurring_cents' => $period->recurringCharges()->where('is_current', true)->sum('amount_cents'), 'new_recurring_cents' => array_sum(array_column($newRecurring, 'amount_cents')), 'new_recurring_count' => count($newRecurring), 'preview_token' => $this->workspace->previewToken($period, $start, $end), 'old_subscription_cents' => $period->commitments()->where('is_current', true)->sum('amount_cents'), 'new_subscription_cents' => array_sum(array_column($newCharges, 'amount_cents')), 'new_charge_count' => count($newCharges), 'transaction_count' => $period->transactions()->count(), 'message' => 'All recorded expenses and received income remain within the proposed dates. Review the changed subscription and recurring forecasts before applying.']);
    }
}
