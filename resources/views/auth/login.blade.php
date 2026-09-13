@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <p class="eyebrow">Private household finance</p>
    <h1 id="page-title" class="auth-title">Sign in</h1>
    <p class="lede auth-lede">Use the email address and password provided by your Moola administrator.</p>

    <form method="post" action="{{ route('login') }}" class="form-stack">
        @csrf

        <div class="field">
            <label for="email">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                inputmode="email"
                required
                autofocus
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
            >
            @error('email')
                <p id="email-error" class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="button">Sign in</button>
    </form>
@endsection
