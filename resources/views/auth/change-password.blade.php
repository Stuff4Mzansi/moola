@extends('layouts.auth')

@section('title', 'Change password')

@section('content')
    <p class="eyebrow">Account security</p>
    <h1 id="page-title" class="auth-title">Choose a new password</h1>
    <p class="lede auth-lede">Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.</p>

    <form method="post" action="{{ route('password.change') }}" class="form-stack">
        @csrf
        @method('put')

        <div class="field">
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            @error('current_password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>

        <button type="submit" class="button">Save password</button>
    </form>
@endsection
