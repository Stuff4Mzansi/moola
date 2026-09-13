@extends('layouts.auth')

@section('title', 'Set up Moola')

@section('content')
    <p class="eyebrow">First-time setup</p>
    <h1 id="page-title" class="auth-title">Create your household</h1>
    <p class="lede auth-lede">This account will be the first administrator. You can invite household members and choose their permissions afterward.</p>

    <form method="post" action="{{ route('setup.store') }}" class="form-stack">
        @csrf

        <div class="field">
            <label for="household_name">Household name</label>
            <input
                id="household_name"
                name="household_name"
                type="text"
                value="{{ old('household_name', 'My Household') }}"
                autocomplete="organization"
                maxlength="120"
                required
                autofocus
            >
            @error('household_name')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="name">Your name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="120" required>
            @error('name')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" maxlength="254" required>
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
            <p class="field-hint">At least 12 characters with uppercase and lowercase letters, a number, and a symbol.</p>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
        </div>

        <button type="submit" class="button">Create administrator</button>
    </form>
@endsection
