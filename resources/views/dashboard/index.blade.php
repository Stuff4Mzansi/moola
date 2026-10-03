@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Welcome, {{ auth()->user()->name }}</h1>
            <p class="mt-2 opacity-70">Your space for managing individual and household finances.</p>
        </div>
        @can('users.manage')
            <div class="card border border-base-300 bg-base-100">
                <div class="card-body items-start">
                    <h2 class="card-title">Manage your household</h2>
                    <p>Add users and assign their roles to get everyone set up.</p>
                    <a class="btn btn-primary mt-2" href="{{ route('admin.users.index') }}">Manage users</a>
                </div>
            </div>
        @endcan
    </div>
@endsection
