<div class="space-y-5" data-budget-live="review">
    <section class="rounded-sm border border-base-300 bg-base-100 p-5" aria-labelledby="period-review-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h3 id="period-review-title" class="text-xl font-semibold">{{ $periodReview['status'] === 'ended' ? 'End-of-period financial review' : 'Period review preview' }}</h3><p class="mt-1 text-sm opacity-65">{{ $period->start_date->format('d M Y') }} - {{ $period->end_date->format('d M Y') }} &middot; Both dates included</p></div>
            <span class="badge badge-outline">{{ ['ended' => 'Period ended', 'current' => 'In progress', 'upcoming' => 'Upcoming period'][$periodReview['status']] }}</span>
        </div>
        <p class="my-3 text-xs opacity-65">{{ $periodReview['status'] === 'ended' ? 'Review your recorded results and confirm unfinished items before starting your next plan.' : 'These are the records entered so far. Results can change until the period has ended and your records are complete.' }}</p>
        @include('budgets.review-spending-trend')
        @include('budgets.review-charts')
        <p class="mt-4 text-xs opacity-65">Spending is grouped by the category assigned to each budget-period transaction. Debt or savings entries recorded only on the Debts or Goals pages are not included here.</p>
        <div class="mt-4 rounded-sm border border-base-300 p-3"><p class="text-xs opacity-65">Received income minus recorded spending</p><p @class(['mt-1 text-xl font-semibold', 'text-error' => $periodReview['recordedBalance'] < 0])>{{ $money($periodReview['recordedBalance']) }}</p><p class="mt-1 text-xs opacity-65">This is a difference between budget records, not your bank balance or money available to spend. Unrecorded payments can change the result.</p></div>
    </section>
    <section class="rounded-sm border border-base-300 bg-base-100 p-5" aria-labelledby="period-review-checklist-title">
        <h3 id="period-review-checklist-title" class="font-semibold">Review before your next period</h3>
        <div class="mt-4 space-y-5">
            <div><h4 class="text-sm font-medium">Scheduled payments not recorded</h4>
                @if($periodReview['pending'])
                    <p class="mt-1 text-xs opacity-65">{{ count($periodReview['pending']) }} payments &middot; {{ $money($periodReview['pendingAmount']) }} estimated. Confirm whether each was paid; a passed date does not prove a missed payment.</p>
                    <ul class="mt-2 divide-y divide-base-300">@foreach($periodReview['pending'] as $payment)<li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"><div><p>{{ $payment['name'] }}</p><p class="text-xs opacity-65">{{ \Carbon\CarbonImmutable::parse($payment['date'])->format('d M Y') }} &middot; {{ $money($payment['amount']) }} estimated</p></div><a class="link link-primary text-xs" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => $payment['tab']]) }}">Review payment</a></li>@endforeach</ul>
                @else<p class="mt-1 text-sm opacity-65">No unpaid scheduled payments in this period's records.</p>@endif
            </div>
            <div><h4 class="text-sm font-medium">Income still to confirm</h4>
                @if($periodReview['missingIncome'])<ul class="mt-2 space-y-1 text-sm">@foreach($periodReview['missingIncome'] as $income)<li>{{ $income['name'] }} &middot; {{ $money($income['amount']) }} expected but not recorded as received</li>@endforeach</ul><a class="mt-2 inline-block link link-primary text-xs" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => 'plan']) }}">Review income</a>
                @else<p class="mt-1 text-sm opacity-65">No outstanding expected income in this period's records.</p>@endif
            </div>
            <div><h4 class="text-sm font-medium">Limits to revisit</h4>
                @if($periodReview['overLimits'])<ul class="mt-2 space-y-1 text-sm">@foreach($periodReview['overLimits'] as $limit)<li>{{ $limit['name'] }} &middot; <span class="text-error">{{ $money($limit['amount']) }} over limit</span></li>@endforeach</ul><a class="mt-2 inline-block link link-primary text-xs" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => 'plan']) }}">Review allocations</a>
                @else<p class="mt-1 text-sm opacity-65">No configured limits exceeded by recorded spending.</p>@endif
            </div>
            <div class="border-t border-base-300 pt-4"><p class="text-sm">Check that everyday expenses are recorded before treating underspending as a final result.</p><a class="mt-2 inline-block link link-primary text-xs" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => 'expenses']) }}">Review recorded expenses</a></div>
        </div>
        @if($canEdit && $periodReview['status'] === 'ended')<div class="mt-5 border-t border-base-300 pt-4"><button class="btn btn-primary" type="button" data-open-dialog="budget-copy">Copy plan to next period</button><p class="mt-2 text-xs opacity-65">Review the dates and allocations before creating the next period. Recorded payments are not copied.</p></div>@endif
    </section>
</div>
