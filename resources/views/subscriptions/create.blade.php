@extends('layouts.app')

@section('title', 'Add subscription')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <a class="link" href="{{ route('subscriptions.index') }}">Back to subscriptions</a>
        <div>
            <h1 class="text-2xl font-bold">Add a subscription</h1>
            <p class="mt-2 opacity-70">Keep track of a recurring expense and its next renewal.</p>
        </div>
        <form method="post" action="{{ route('subscriptions.store') }}" class="card border border-base-300 bg-base-100">
            @csrf
            @include('subscriptions.form')
        </form>
    </div>
@endsection
