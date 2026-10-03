@extends('layouts.app')

@section('title', 'Add user')

@section('content')
    <div class="mx-auto max-w-xl space-y-6">
        <a class="link" href="{{ route('admin.users.index') }}">Back to household users</a>
        <div>
            <h1 class="text-2xl font-bold">Add a household user</h1>
            <p class="mt-2 opacity-70">Create an account and share the sign-in details with them privately.</p>
        </div>
        <form method="post" action="{{ route('admin.users.store') }}" class="card border border-base-300 bg-base-100">
            @csrf
            <div class="card-body gap-4">
                @if($errors->any())
                    <div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <div>
                    <label class="label" for="name">Full name</label>
                    <input class="input w-full" id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required>
                </div>
                <div>
                    <label class="label" for="email">Email address</label>
                    <input class="input w-full" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
                </div>
                <div>
                    <label class="label" for="role">Role</label>
                    <select class="select w-full" id="role" name="role" required>
                        <option value="member" @selected(old('role', 'member') === 'member')>Member</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                    </select>
                    <p class="mt-1 text-sm opacity-70">Admins can add users, assign roles, and remove other users.</p>
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input class="input w-full" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-help">
                    <p id="password-help" class="mt-1 text-sm opacity-70">Use at least 12 characters.</p>
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm password</label>
                    <input class="input w-full" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                </div>
                <button class="btn btn-primary" type="submit">Add user</button>
            </div>
        </form>
    </div>
@endsection
