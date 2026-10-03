@extends('layouts.app')

@section('title', $subscription->name)

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <a class="link" href="{{ route('subscriptions.index') }}">Back to subscriptions</a>
        @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">{{ $subscription->name }}</h1>
                <div class="mt-3 flex items-center gap-3">
                    <span @class(['badge', 'badge-success' => $subscription->status === \App\SubscriptionStatus::Active, 'badge-warning' => $subscription->status === \App\SubscriptionStatus::Paused, 'badge-ghost' => $subscription->status === \App\SubscriptionStatus::Cancelled])>{{ $subscription->status->label() }}</span>
                    @if($subscription->category)<span class="text-sm opacity-70">{{ $subscription->category }}</span>@endif
                </div>
            </div>
            <a class="btn btn-primary" href="{{ route('subscriptions.edit', $subscription) }}">Edit subscription</a>
        </div>
        <div class="card border border-base-300 bg-base-100">
            <div class="card-body gap-6">
                <div><p class="text-sm opacity-70">Price per payment</p><p class="mt-1 text-3xl font-bold">ZAR {{ number_format($subscription->amount_cents / 100, 2) }} <span class="text-base font-normal opacity-70">{{ strtolower($subscription->billing_frequency->label()) }}</span></p></div>
                <dl class="grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-sm opacity-70">Next renewal</dt><dd class="mt-1 font-semibold">{{ $subscription->nextRenewalDate()?->format('d M Y') ?? 'No upcoming renewal' }}</dd></div>
                    <div><dt class="text-sm opacity-70">Billing schedule from</dt><dd class="mt-1 font-semibold">{{ $subscription->next_billing_date->format('d M Y') }}</dd></div>
                    @if($subscription->status === \App\SubscriptionStatus::Active)
                        <div><dt class="text-sm opacity-70">Monthly equivalent</dt><dd class="mt-1 font-semibold">ZAR {{ number_format($subscription->monthlyCostCents() / 100, 2) }}</dd></div>
                        <div><dt class="text-sm opacity-70">Annual equivalent</dt><dd class="mt-1 font-semibold">ZAR {{ number_format($subscription->annualCostCents() / 100, 2) }}</dd></div>
                    @endif
                    @if($subscription->website)<div class="sm:col-span-2"><dt class="text-sm opacity-70">Website</dt><dd class="mt-1 break-all"><a class="link link-primary" href="{{ $subscription->website }}" target="_blank" rel="noopener noreferrer">{{ $subscription->website }}</a></dd></div>@endif
                    @if($subscription->notes)<div class="sm:col-span-2"><dt class="text-sm opacity-70">Notes</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $subscription->notes }}</dd></div>@endif
                </dl>
                <p class="text-sm opacity-70">Renewals are expected payments, not confirmed transactions. Cancel the service with your provider before marking it cancelled here.</p>
            </div>
        </div>
        <div class="flex justify-end"><button class="btn btn-outline btn-error" type="button" onclick="document.getElementById('delete-subscription').showModal()">Delete subscription</button></div>
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
