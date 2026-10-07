<section class="space-y-3" aria-labelledby="budget-charges-title">
    <header class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 id="budget-charges-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-repeat class="size-4 text-primary" aria-hidden="true" />Subscription charges</h3>
            <p class="mt-1 text-xs opacity-65">Payments scheduled inside this period's dates, from the budget owner's subscriptions.</p>
        </div>
        @if($budget->scope === 'personal' && $isOwner)
            <a class="btn btn-sm btn-outline" href="{{ route('subscriptions.index') }}">Manage your subscriptions <x-lucide-arrow-up-right class="size-3.5" aria-hidden="true" /></a>
        @endif
    </header>
    @include('budgets.scheduled-summary', ['scheduledCharges' => $commitments, 'summaryLabel' => 'Subscription payments for this period'])
    <div class="max-h-96 overflow-auto rounded-sm border border-base-300 bg-base-100 focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2" tabindex="0" role="region" aria-label="Subscription charges; scroll to view all payments and columns">
        <table class="table table-sm w-full">
            <caption class="sr-only">Subscription forecasts and recorded payments for {{ $period->name }}. Scheduled dates are shown in ascending order.</caption>
            <thead class="sticky top-0 z-10 bg-base-200 text-xs">
                <tr><th scope="col">Subscription</th><th scope="col" aria-sort="ascending">Scheduled date</th><th scope="col" class="text-right">Scheduled amount</th><th scope="col" class="text-right">Recorded payment</th><th scope="col">Status / action</th></tr>
            </thead>
            <tbody>
                @forelse($commitments as $charge)
                    <tr class="hover:bg-base-200/40">
                        <th scope="row" class="min-w-40 max-w-64 whitespace-normal break-words py-3 font-semibold">{{ $charge->name }}</th>
                        <td class="whitespace-nowrap"><time datetime="{{ $charge->scheduled_date->toDateString() }}">{{ $charge->scheduled_date->format('d M Y') }}</time></td>
                        <td class="whitespace-nowrap text-right tabular-nums">{{ $money($charge->amount_cents) }}</td>
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
                                <button class="btn btn-xs btn-outline rounded-sm whitespace-nowrap" type="button" data-open-dialog="budget-payment" data-pay-charge="{{ $charge->id }}" data-charge-amount="{{ $amount($charge->amount_cents) }}" data-charge-name="{{ $charge->name }}" aria-label="Record payment for {{ $charge->name }}, scheduled {{ $charge->scheduled_date->format('d M Y') }}">Record payment</button>
                            @else
                                <span class="badge badge-sm badge-outline">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-sm opacity-65">No subscription charges scheduled for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="flex items-start gap-1.5 text-xs leading-snug opacity-65"><x-lucide-info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" /><span>Unpaid charges are forecasts. Paid charges appear once in spending; the recorded amount may differ from the scheduled amount.</span></p>
</section>
