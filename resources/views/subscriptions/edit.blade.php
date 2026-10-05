@extends('layouts.app')

@section('title', 'Edit subscription')

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <a class="link link-hover text-xs" href="{{ route('subscriptions.show', $subscription) }}">Back to subscription</a>
        <div>
            <h1 class="text-2xl font-bold">Edit {{ $subscription->name }}</h1>
            <p class="mt-1 text-sm opacity-65">Update the price, billing schedule, or subscription status.</p>
        </div>
        <form method="post" action="{{ route('subscriptions.update', $subscription) }}" class="card border border-base-300 bg-base-100">
            @csrf
            @method('PATCH')
            @include('subscriptions.form')
        </form>
    </div>
@endsection
