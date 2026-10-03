@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div>
        <h1 class="text-2xl font-bold">Sign in to Moola</h1>
        <p class="mt-2 opacity-70">Access your finances and plan ahead.</p>
    </div>
    <form method="post" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email address</label>
            <input class="input w-full" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p id="email-error" class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input class="input w-full" id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password')<p id="password-error" class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-3"><input class="checkbox checkbox-sm" type="checkbox" name="remember" value="1" @checked(old('remember'))>Remember me</label>
        <button class="btn btn-primary w-full" type="submit">Sign in</button>
    </form>
    <p class="text-sm opacity-70">Need an account? Ask your household admin to add you.</p>
@endsection
