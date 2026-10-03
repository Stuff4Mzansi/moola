@extends('layouts.guest')

@section('title', 'Set up Moola')

@section('content')
    <div>
        <h1 class="text-2xl font-bold">Welcome to Moola</h1>
        <p class="mt-2 opacity-70">Create your super admin account to get started. You can add other household users after setup.</p>
    </div>
    <form method="post" action="{{ route('setup.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="name">Full name</label>
            <input class="input w-full" id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required autofocus @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p id="name-error" class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="email">Email address</label>
            <input class="input w-full" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p id="email-error" class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input class="input w-full" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
            <p id="password-help" class="mt-1 text-sm opacity-70">Use at least 12 characters.</p>
            @error('password')<p id="password-error" class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirm password</label>
            <input class="input w-full" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
        </div>
        <button class="btn btn-primary w-full" type="submit">Create super admin account</button>
    </form>
@endsection
