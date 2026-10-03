@extends('layouts.app')

@section('title', 'Household users')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Household users</h1>
                <p class="mt-2 opacity-70">Manage who can sign in and administer your Moola app.</p>
            </div>
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Add user</a>
        </div>
        @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user->name }} @if(auth()->user()->is($user))<span class="badge badge-ghost badge-sm">You</span>@endif</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge badge-outline">{{ $user->role->label() }}</span></td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    @can('users.assign-role', $user)
                                        <form method="post" action="{{ route('admin.users.update', $user) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="sr-only" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                                            <select id="role-{{ $user->id }}" name="role" class="select select-sm">
                                                <option value="member" @selected($user->role === \App\UserRole::Member)>Member</option>
                                                <option value="admin" @selected($user->role === \App\UserRole::Admin)>Admin</option>
                                            </select>
                                            <button class="btn btn-sm" type="submit">Save role</button>
                                        </form>
                                    @endcan
                                    @can('users.delete', $user)
                                        <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user? They will lose access to Moola and their subscriptions will be permanently deleted.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline btn-error" type="submit" aria-label="Delete {{ $user->name }}">Delete</button>
                                        </form>
                                    @endcan
                                    @if($user->isSuperAdmin())<span class="text-sm opacity-70">Protected owner account</span>@endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
