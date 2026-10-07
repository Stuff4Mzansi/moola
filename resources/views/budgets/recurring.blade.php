<div class="space-y-3">
    <header class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-repeat class="size-4 text-primary" aria-hidden="true" />Recurring expenses</h3>
            <p class="mt-1 text-xs opacity-65">Regular bills scheduled in this period, with estimates based on your payment history.</p>
        </div>
        @if($canEdit)<button class="btn btn-primary btn-sm" type="button" data-open-dialog="budget-recurring-edit" data-new-recurring><x-lucide-plus class="size-3.5" aria-hidden="true" />Add recurring expense</button>@endif
    </header>
    @include('budgets.scheduled-summary', ['scheduledCharges' => $recurringCharges, 'summaryLabel' => 'Recurring payments for this period'])
    <section class="space-y-2" aria-labelledby="budget-recurring-due-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 id="budget-recurring-due-title" class="text-sm font-semibold">Scheduled in this period</h4>
            <span class="text-xs opacity-65">{{ $recurringCharges->count() }} payments &middot; Earliest first</span>
        </div>
        @if($canEdit && $period->end_date->lt(now()->startOfDay()) && $recurringExpenses->isNotEmpty())
            <div class="flex flex-wrap items-center gap-3 rounded-sm border border-base-300 bg-base-200/40 p-3">
                <form method="post" action="{{ $actionUrl('recurring-load') }}" data-budget-action>@csrf<input type="hidden" name="version" value="{{ $period->version }}"><button type="submit" class="btn btn-sm btn-outline">Load / update past occurrences</button></form>
                <p class="min-w-0 flex-1 basis-64 text-xs opacity-65">Load schedules to record old payments or link existing bills. Recorded payments are preserved; unpaid forecasts use the current schedules.</p>
            </div>
        @endif
        <div class="max-h-96 overflow-auto rounded-sm border border-base-300 bg-base-100 focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2" tabindex="0" role="region" aria-label="Recurring payments; scroll to view all payments and columns">
            <table class="table table-sm w-full">
                <caption class="sr-only">Recurring expense forecasts and recorded payments for {{ $period->name }}. Expand Estimate details for the basis of each unpaid forecast.</caption>
                <thead class="sticky top-0 z-10 bg-base-200 text-xs">
                    <tr><th scope="col">Expense / category</th><th scope="col" aria-sort="ascending">Scheduled date</th><th scope="col" class="text-right">Scheduled estimate</th><th scope="col" class="text-right">Recorded payment</th><th scope="col">Status / action</th></tr>
                </thead>
                <tbody>
                    @forelse($recurringCharges as $charge)
                        <tr class="hover:bg-base-200/40">
                            <th scope="row" class="min-w-40 max-w-64 whitespace-normal break-words py-3 font-normal">
                                <p class="font-semibold">{{ $charge->name }}</p>
                                <p class="mt-0.5 text-xs opacity-65">{{ $categories->firstWhere('id', $charge->budget_category_id)?->name ?? 'Other' }}</p>
                            </th>
                            <td class="whitespace-nowrap"><time datetime="{{ $charge->scheduled_date->toDateString() }}">{{ $charge->scheduled_date->format('d M Y') }}</time></td>
                            <td class="min-w-48 max-w-72 text-right">
                                <p class="whitespace-nowrap tabular-nums">{{ $money($charge->amount_cents) }}</p>
                                @if(! $charge->transaction)
                                    <details class="mt-1 text-left text-xs">
                                        <summary class="cursor-pointer text-primary focus-visible:outline-2 focus-visible:outline-primary">Estimate details<span class="sr-only"> for {{ $charge->name }}, {{ $charge->scheduled_date->format('d M Y') }}</span></summary>
                                        <p class="mt-1 whitespace-normal leading-snug opacity-65">{{ $charge->debt_id !== null ? 'Debt minimum payment. Interest is estimated automatically; you can enter the actual split in Debts.' : ($charge->history_payments > 0 ? 'Average of '.$charge->history_payments.' payments across '.$charge->history_months.' of the preceding three completed months.' : 'Starting estimate. No payments recorded in the prior three completed months.') }}</p>
                                    </details>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right tabular-nums">
                                @if($charge->transaction)
                                    <p class="font-semibold text-orange-600">{{ $money($charge->transaction->amount_cents) }}</p>
                                    <p class="mt-0.5 text-xs opacity-65">Paid on {{ $charge->transaction->date->format('d M Y') }}</p>
                                @else
                                    <span class="text-xs opacity-65">Not recorded</span>
                                @endif
                            </td>
                            <td>
                                @if($charge->transaction)
                                    <span class="badge badge-sm badge-ghost">Paid</span>
                                @elseif($canEdit && $charge->is_current)
                                    <button class="btn btn-xs btn-outline rounded-sm whitespace-nowrap" type="button" data-open-dialog="budget-recurring-payment" data-pay-recurring="{{ $charge->id }}" data-charge-amount="{{ $amount($charge->amount_cents) }}" data-charge-name="{{ $charge->name }}" aria-label="Record payment for {{ $charge->name }}, scheduled {{ $charge->scheduled_date->format('d M Y') }}">Record payment</button>
                                @else
                                    <span class="badge badge-sm badge-outline">Unpaid</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-sm opacity-65">No recurring payments scheduled inside this period's dates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="flex items-start gap-1.5 text-xs leading-snug opacity-65"><x-lucide-info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" /><span>Schedules generate forecasts and suggestions, not automatic payments. Recorded payments appear once in spending.</span></p>
    </section>
    <details class="rounded-sm border border-base-300 bg-base-100" @if($recurringCharges->isEmpty()) open @endif>
        <summary class="cursor-pointer px-3 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-primary">Your schedules <span class="ml-1 text-xs font-normal opacity-65">{{ $recurringExpenses->count() }} total &middot; {{ $recurringExpenses->where('is_active', true)->count() }} active &middot; Manage regular bills</span></summary>
        <section class="space-y-2 border-t border-base-300 p-3" aria-labelledby="budget-recurring-schedules-title">
            <h4 id="budget-recurring-schedules-title" class="sr-only">Your schedules</h4>
            <p class="text-xs opacity-65">Schedules apply across this budget's periods. Starting estimates are replaced by payment-history averages when available.</p>
            <div class="max-h-80 overflow-auto rounded-sm border border-base-300 focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2" tabindex="0" role="region" aria-label="Recurring schedules; scroll to view all schedules and columns">
                <table class="table table-sm w-full">
                    <caption class="sr-only">All recurring expense schedules for this budget, including paused schedules.</caption>
                    <thead class="sticky top-0 z-10 bg-base-200 text-xs"><tr><th scope="col">Schedule / category</th><th scope="col">Frequency</th><th scope="col" class="text-right">Starting estimate</th><th scope="col">Dates</th><th scope="col">Status</th><th scope="col">Management</th></tr></thead>
                    <tbody>
                        @forelse($recurringExpenses as $expense)
                            @php $recurringCategory = $categories->firstWhere('name', $expense->category_name) ?? $categories->firstWhere('kind', 'other'); @endphp
                            <tr class="hover:bg-base-200/40">
                                <th scope="row" class="min-w-40 max-w-64 whitespace-normal break-words py-3 font-normal"><p class="font-semibold">{{ $expense->name }}</p><p class="mt-0.5 text-xs opacity-65">{{ $expense->category_name }}</p></th>
                                <td class="whitespace-nowrap">{{ $expense->billing_frequency->label() }}</td>
                                <td class="whitespace-nowrap text-right tabular-nums">{{ $money($expense->amount_cents) }}</td>
                                <td class="whitespace-nowrap text-xs">From {{ $expense->start_date->format('d M Y') }}<p class="mt-0.5 opacity-65">{{ $expense->end_date ? 'To '.$expense->end_date->format('d M Y') : 'No end date' }}</p></td>
                                <td><span class="badge badge-sm badge-ghost">{{ $expense->is_active ? 'Active' : 'Paused' }}</span></td>
                                <td>
                                    @if($expense->debt_id !== null && config('features.debt_tracking'))
                                        <p class="text-xs opacity-65">Debt-linked schedule.</p>
                                        @if($budget->user_id === auth()->id())<a class="link link-primary text-xs" href="{{ route('debts.index') }}">Manage in Debts</a>@endif
                                    @elseif($canEdit)
                                        <div class="flex gap-1">
                                            <button class="btn btn-sm btn-ghost" type="button" data-open-dialog="budget-recurring-edit" aria-label="Edit {{ $expense->name }} schedule" data-edit-recurring="{{ json_encode(['id' => $expense->id, 'name' => $expense->name, 'category_id' => $recurringCategory?->id, 'amount' => $amount($expense->amount_cents), 'billing_frequency' => $expense->billing_frequency->value, 'start_date' => $expense->start_date->toDateString(), 'end_date' => $expense->end_date?->toDateString(), 'is_active' => $expense->is_active ? 1 : 0]) }}">Edit</button>
                                            <form action="{{ $actionUrl('recurring-remove') }}" method="post" data-budget-action data-confirm="Remove the {{ $expense->name }} recurring schedule? Future unpaid forecasts will be removed. Recorded payments are kept." data-confirm-title="Remove recurring expense?" data-confirm-label="Remove schedule">@csrf<input type="hidden" name="version" value="{{ $period->version }}"><input type="hidden" name="id" value="{{ $expense->id }}"><button class="btn btn-sm btn-ghost text-error" type="submit" aria-label="Remove {{ $expense->name }} schedule">Remove</button></form>
                                        </div>
                                    @else
                                        <span class="text-xs opacity-65">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-sm opacity-65">Start with regular costs such as rent, electricity, groceries, or transport. Schedules generate forecasts and suggestions, not automatic payments.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </details>
</div>
