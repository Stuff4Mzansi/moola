@extends('layouts.app')

@section('title', $subscription->name)

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <a class="link link-hover text-xs" href="{{ route('subscriptions.index') }}">Back to subscriptions</a>
        @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ $subscription->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span @class(['badge badge-sm', 'badge-success' => $subscription->status === \App\SubscriptionStatus::Active, 'badge-warning' => $subscription->status === \App\SubscriptionStatus::Paused, 'badge-ghost' => $subscription->status === \App\SubscriptionStatus::Cancelled])>{{ $subscription->status->label() }}</span>
                    @if($subscription->category)<span class="text-[11px] leading-snug opacity-65">{{ $subscription->category }}</span>@endif
                </div>
            </div>
            <a class="btn btn-primary" href="{{ route('subscriptions.edit', $subscription) }}">Edit subscription</a>
        </div>
        <div class="card border border-base-300 bg-base-100">
            <div class="space-y-4 p-4">
                <div><p class="text-[11px] leading-snug opacity-65">Price per payment</p><p class="mt-1 text-xl font-semibold tabular-nums">ZAR {{ number_format($subscription->amount_cents / 100, 2) }} <span class="text-xs font-normal opacity-60">{{ strtolower($subscription->billing_frequency->label()) }}</span></p></div>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-[11px] leading-snug opacity-65">Next renewal</dt><dd class="mt-1 text-sm font-semibold tabular-nums">{{ $subscription->nextRenewalDate()?->format('d M Y') ?? 'No upcoming renewal' }}</dd></div>
                    <div><dt class="text-[11px] leading-snug opacity-65">Billing schedule from</dt><dd class="mt-1 text-sm font-semibold tabular-nums">{{ $subscription->next_billing_date->format('d M Y') }}</dd></div>
                    @if($subscription->status === \App\SubscriptionStatus::Active)
                        <div><dt class="text-[11px] leading-snug opacity-65">Monthly equivalent</dt><dd class="mt-1 text-sm font-semibold tabular-nums">ZAR {{ number_format($subscription->monthlyCostCents() / 100, 2) }}</dd></div>
                        <div><dt class="text-[11px] leading-snug opacity-65">Annual equivalent</dt><dd class="mt-1 text-sm font-semibold tabular-nums">ZAR {{ number_format($subscription->annualCostCents() / 100, 2) }}</dd></div>
                    @endif
                    @if($subscription->website)<div class="sm:col-span-2"><dt class="text-[11px] leading-snug opacity-65">Website</dt><dd class="mt-1 break-all text-xs"><a class="link link-primary" href="{{ $subscription->website }}" target="_blank" rel="noopener noreferrer">{{ $subscription->website }}</a></dd></div>@endif
                    @if($subscription->notes)<div class="sm:col-span-2"><dt class="text-[11px] leading-snug opacity-65">Notes</dt><dd class="mt-1 whitespace-pre-wrap break-words text-xs">{{ $subscription->notes }}</dd></div>@endif
                </dl>
                <p class="text-[11px] leading-snug opacity-65">Renewals are expected payments, not confirmed transactions. Cancel the service with your provider before marking it cancelled here.</p>
            </div>
        </div>
        <div class="flex justify-end"><button class="btn btn-outline btn-error btn-sm" type="button" onclick="document.getElementById('delete-subscription').showModal()">Delete subscription</button></div>
        <dialog id="delete-subscription" class="modal" aria-labelledby="delete-title">
            <div class="modal-box">
                <h2 id="delete-title" class="text-lg font-bold">Delete {{ $subscription->name }}?</h2>
                <p class="py-4">This removes the subscription from Moola and its forecasts. It does not cancel the service with your provider.</p>
                <div class="modal-action">
                    <form method="dialog"><button class="btn" type="submit">Keep subscription</button></form>
                    <form method="post" action="{{ route('subscriptions.destroy', $subscription) }}">@csrf @method('DELETE')<button class="btn btn-error" type="submit">Delete subscription</button></form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button type="submit">Close</button></form>
        </dialog>
    </div>
@endsection
