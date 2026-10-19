<section id="budget-transactions" class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-base-300 bg-base-100 p-3">
        <div class="flex items-center gap-2">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-sm bg-error/10 text-error"><x-lucide-receipt-text class="size-4" aria-hidden="true" /></span>
            <div><h3 class="text-sm font-semibold">Recorded expenses <span class="text-xs font-normal opacity-60">({{ $transactions->total() }})</span></h3><p class="mt-0.5 text-xs opacity-65">Recorded in this period; search or filter to find a transaction.</p></div>
        </div>
        <form action="{{ route('budgets.index') }}" method="get" class="flex flex-wrap items-end gap-2" data-budget-expense-filters>
            <input type="hidden" name="period" value="{{ $period->id }}">
            <input type="hidden" name="tab" value="expenses">
            <label>
                <span class="sr-only">Search expenses or categories</span>
                <input class="input input-sm" name="expense_search" value="{{ $expenseSearch }}" placeholder="Search expenses or categories" maxlength="100">
            </label>
            <label>
                <span class="sr-only">Filter expenses by category</span>
                <select class="select select-sm" name="expense_category" aria-label="Filter expenses by category">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $category->id === $expenseCategory)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn btn-sm btn-outline" type="submit">Search</button>
            @if($hasExpenseFilters)
                <a class="btn btn-sm btn-ghost" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => 'expenses']) }}">Clear filters</a>
            @endif
        </form>
    </header>
    <div class="max-h-[32rem] overflow-auto rounded-sm border border-base-300 bg-base-100" role="region" aria-label="Recorded budget expenses" tabindex="0">
        <table class="table">
            <thead class="sticky top-0 z-10 bg-base-200 text-xs"><tr><th scope="col" aria-sort="descending"><span class="inline-flex items-center gap-1.5">Date <x-lucide-arrow-down class="size-3.5" aria-hidden="true" /><span class="sr-only">Newest first</span></span></th><th scope="col">Expense</th><th scope="col">Category</th><th scope="col" class="text-right">Amount</th>@if($canEdit)<th scope="col">Actions</th>@endif</tr></thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php $transactionCategory = $categories->firstWhere('id', $transaction->budget_category_id); @endphp
                    <tr data-budget-expense-row data-category="{{ $transaction->budget_category_id }}">
                        <td class="whitespace-nowrap">{{ $transaction->date->format('d M Y') }}</td>
                        <td class="min-w-40 whitespace-normal break-words">{{ $transaction->description ?: 'Expense' }}@if($transaction->savings_goal_id)<span class="badge badge-ghost badge-sm rounded-sm">Savings contribution</span>@endif @if($transaction->budget_recurring_charge_id)<span class="badge badge-ghost badge-sm">Recurring payment</span>@endif @if($transaction->budget_commitment_id)<span class="badge badge-ghost badge-sm">Subscription payment</span>@endif</td>
                        <td>{{ $transactionCategory?->name ?? 'Other' }}</td>
                        <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ $money($transaction->amount_cents) }}</td>
                        @if($canEdit)
                            <td><div class="flex gap-2"><button class="btn btn-xs" type="button" data-open-dialog="budget-expense" data-edit-expense="{{ json_encode(['id' => $transaction->id, 'recurring_charge_id' => $transaction->budget_recurring_charge_id, 'category_id' => $transaction->budget_category_id, 'amount' => $amount($transaction->amount_cents), 'date' => $transaction->date->toDateString(), 'description' => $transaction->description, 'linked' => $transaction->budget_commitment_id !== null]) }}">Edit</button><form method="post" action="{{ $actionUrl('expense-remove') }}" data-budget-action data-confirm="Delete this expense? It will be removed from budget spending. You can undo this deletion." data-confirm-title="Delete expense?" data-confirm-label="Delete expense">@csrf<input type="hidden" name="version" value="{{ $period->version }}"><input type="hidden" name="id" value="{{ $transaction->id }}"><button class="btn btn-xs btn-ghost" type="submit">Delete</button></form></div></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="p-8 text-center opacity-70">{{ $hasExpenseFilters ? 'No expenses match these filters.' : 'No expenses recorded yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->total() > 0)
        <nav class="flex flex-wrap items-center justify-between gap-2 text-xs" aria-label="Expense pages">
            <p class="tabular-nums opacity-65">Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }} expenses</p>
            @if($transactions->hasPages())
                <div class="flex items-center gap-2">
                    @if($transactions->onFirstPage())
                        <span class="btn btn-xs btn-ghost" aria-disabled="true">Previous</span>
                    @else
                        <a class="btn btn-xs btn-outline" href="{{ $transactions->previousPageUrl() }}">Previous</a>
                    @endif
                    <span class="tabular-nums opacity-65">Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</span>
                    @if($transactions->hasMorePages())
                        <a class="btn btn-xs btn-outline" href="{{ $transactions->nextPageUrl() }}">Next</a>
                    @else
                        <span class="btn btn-xs btn-ghost" aria-disabled="true">Next</span>
                    @endif
                </div>
            @endif
        </nav>
    @endif
</section>
