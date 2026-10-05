@extends('layouts.app')

@section('title', 'Add subscription')

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <a class="link link-hover text-xs" href="{{ route('subscriptions.index') }}">Back to subscriptions</a>
        <div>
            <h1 class="text-2xl font-bold">Add a subscription</h1>
            <p class="mt-1 text-sm opacity-65">Keep track of a recurring expense and its next renewal.</p>
        </div>
        <form method="post" action="{{ route('subscriptions.store') }}" class="card border border-base-300 bg-base-100">
            @csrf
            @include('subscriptions.form')
        </form>
    </div>
@endsection
