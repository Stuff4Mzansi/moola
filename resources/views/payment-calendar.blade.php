@extends('layouts.app')
@section('title', 'Payment calendar')
@section('content')
@php
    $money = fn (int $cents): string => 'ZAR '.number_format($cents / 100, 2);
    $types = ['subscription' => ['label' => 'Subscription', 'class' => 'badge-info'], 'recurring' => ['label' => 'Recurring bill', 'class' => 'badge-primary'], 'debt' => ['label' => 'Debt minimum', 'class' => 'badge-warning'], 'income' => ['label' => 'Income', 'class' => 'badge-success']];
    $calendarUrl = fn (\Carbon\CarbonImmutable $date): string => route('payment-calendar.index', ['month' => $date->format('Y-m'), 'type' => $type]);
@endphp
<div class="mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-bold">Payment calendar</h1><p class="mt-1 text-sm opacity-65">See what is coming in and going out, all in one place.</p></div><a class="btn btn-outline" href="{{ $calendarUrl($today->startOfMonth()) }}">This month</a></header>
    @if($errors->any())<div class="alert alert-error text-sm" role="alert">{{ $errors->first() }}</div>@endif
    <section class="rounded-sm border border-base-300 bg-base-100 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                @if($month->gt($today->startOfMonth()))<a class="btn btn-square btn-sm btn-ghost" href="{{ $calendarUrl($month->subMonth()) }}" aria-label="Previous month"><x-lucide-chevron-left class="size-4" /></a>@else<span class="btn btn-square btn-sm btn-ghost btn-disabled" aria-hidden="true"><x-lucide-chevron-left class="size-4" /></span>@endif
                <h2 class="text-lg font-semibold">{{ $month->format('F Y') }}</h2>
                @if($month->lt($today->startOfMonth()->addMonths(11)))<a class="btn btn-square btn-sm btn-ghost" href="{{ $calendarUrl($month->addMonth()) }}" aria-label="Next month"><x-lucide-chevron-right class="size-4" /></a>@endif
            </div>
            <form method="get" action="{{ route('payment-calendar.index') }}" class="flex flex-wrap items-end gap-2"><label><span class="label text-xs">Month</span><input type="month" name="month" class="input input-sm" value="{{ $month->format('Y-m') }}" min="{{ $today->format('Y-m') }}" max="{{ $today->addMonthsNoOverflow(11)->format('Y-m') }}" required></label><label><span class="label text-xs">Show</span><select name="type" class="select select-sm"><option value="all" @selected($type === 'all')>All events</option>@foreach($types as $key => $eventType)<option value="{{ $key }}" @selected($type === $key)>{{ $eventType['label'] }}</option>@endforeach</select></label><button type="submit" class="btn btn-primary btn-sm">Apply</button></form>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-sm bg-success/10 p-3"><p class="text-xs opacity-65">Expected incoming</p><p class="mt-1 text-2xl font-semibold text-success">{{ $money($incoming) }}</p></div>
            <div class="rounded-sm bg-primary/10 p-3"><p class="text-xs opacity-65">Scheduled outgoing</p><p class="mt-1 text-2xl font-semibold">{{ $money($outgoing) }}</p></div>
            <div class="rounded-sm bg-base-200/60 p-3"><p class="text-xs opacity-65">Upcoming events</p><p class="mt-1 text-2xl font-semibold">{{ $events->count() }}</p><p class="text-xs opacity-65">{{ $month->isSameMonth($today) ? 'From today to month end' : 'Across the selected month' }}</p></div>
        </div>
        <p class="mt-3 text-xs opacity-65">Estimates in ZAR, not confirmed payments or a cash balance. Recorded payments and received income are excluded. Your subscriptions and debts are combined with budgets you own or can view. Separate budgets can describe the same income or bills; check their scope before adding totals.</p>
        @if(($undatedIncome > 0 && in_array($type, ['all', 'income'], true)) || $foreignSubscriptions > 0)<div class="mt-3 rounded-sm bg-warning/10 p-3 text-xs">@if($undatedIncome > 0 && in_array($type, ['all', 'income'], true))<p>{{ $undatedIncome }} outstanding income entries in this month's budget periods need an expected date before they can appear here. Set dates on each budget's Plan tab.</p>@endif @if($foreignSubscriptions > 0)<p>{{ $foreignSubscriptions }} of your active subscriptions use another currency and are excluded. No exchange rate is assumed.</p>@endif</div>@endif
    </section>
    <section class="hidden overflow-x-auto rounded-sm border border-base-300 bg-base-100 sm:block" aria-label="Monthly payment calendar">
        <div class="grid min-w-[700px] grid-cols-7 border-b border-base-300 bg-base-200/50">@foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<div class="p-3 text-center text-xs font-semibold">{{ $weekday }}</div>@endforeach</div>
        <div class="grid min-w-[700px] grid-cols-7">
            @foreach($days as $day)
                @php $date = $day['date']; $dayEvents = $day['events']; @endphp
                <div @class(['min-h-28 min-w-0 border-b border-r border-base-300 p-2', 'bg-base-200/40 opacity-50' => ! $date->isSameMonth($month) || $date->lt($today), 'bg-primary/5' => $date->isSameDay($today)])>
                    <div class="flex items-center justify-between gap-1"><span @class(['flex size-6 items-center justify-center rounded-sm text-xs font-medium', 'bg-primary text-primary-content' => $date->isSameDay($today)])>{{ $date->day }}</span>@if($dayEvents->isNotEmpty())<a class="link text-xs" href="#agenda-{{ $date->toDateString() }}" aria-label="{{ $dayEvents->count() }} events on {{ $date->format('d F Y') }}">{{ $dayEvents->count() }} events</a>@endif</div>
                    @foreach($dayEvents->take(2) as $event)<a class="mt-1 block rounded-sm bg-base-200/70 px-1.5 py-1 text-[10px] leading-tight hover:bg-base-300/70" href="{{ $event['url'] }}"><span class="block truncate font-medium" title="{{ $event['name'] }}">{{ $event['name'] }}</span><span @class(['block mt-0.5', 'text-success' => $event['type'] === 'income'])>{{ $event['type'] === 'income' ? '+' : '' }}{{ $money($event['amount']) }}</span></a>@endforeach
                    @if($dayEvents->count() > 2)<a class="mt-1 block link text-[10px]" href="#agenda-{{ $date->toDateString() }}">+{{ $dayEvents->count() - 2 }} more</a>@endif
                </div>
            @endforeach
        </div>
    </section>
    <section class="rounded-sm border border-base-300 bg-base-100 p-4 sm:p-5" aria-labelledby="payment-agenda-title">
        <h2 id="payment-agenda-title" class="text-lg font-semibold">Upcoming agenda</h2>
        @forelse($byDay as $date => $dayEvents)
            <div class="mt-5 scroll-mt-20 border-t border-base-300 pt-4" id="agenda-{{ $date }}"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-semibold">{{ \Carbon\CarbonImmutable::parse($date)->format('l, d F') }} @if($date === $today->toDateString())<span class="badge badge-sm badge-primary ml-1">Today</span>@endif</h3><span class="text-xs opacity-65">{{ $dayEvents->count() }} events</span></div><ul class="mt-2 divide-y divide-base-300">@foreach($dayEvents as $event)<li><a class="flex flex-wrap items-center justify-between gap-3 rounded-sm px-2 py-3 hover:bg-base-200/60" href="{{ $event['url'] }}"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="badge badge-sm badge-soft {{ $types[$event['type']]['class'] }}">{{ $types[$event['type']]['label'] }}</span><span class="break-words text-sm font-medium">{{ $event['name'] }}</span></div><p class="mt-1 text-xs opacity-65">{{ $event['scope'] }} &middot; {{ $event['type'] === 'income' ? 'Remaining expected income' : 'Scheduled estimate' }}</p></div><span @class(['shrink-0 text-sm font-semibold tabular-nums', 'text-success' => $event['type'] === 'income'])>{{ $event['type'] === 'income' ? '+' : '' }}{{ $money($event['amount']) }} <span aria-hidden="true">&rarr;</span></span></a></li>@endforeach</ul></div>
        @empty<div class="mt-4 rounded-sm border border-dashed border-base-300 p-8 text-center"><x-lucide-calendar-days class="mx-auto size-8 text-primary/60" /><p class="mt-3 font-medium">No upcoming events for this selection</p><p class="mt-1 text-sm opacity-65">Try another month or event type, or add schedules and dated income to your records.</p><a class="btn btn-outline btn-sm mt-3" href="{{ route('budgets.index') }}">Open budgets</a></div>@endforelse
    </section>
</div>
@endsection
