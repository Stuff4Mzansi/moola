@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-semibold">Notifications</h1><p class="mt-1 text-sm opacity-60">Reminders and budget warnings, with your history in one place.</p></div><form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline" type="submit">Mark all read</button></form></div>
    @if(session('status'))<div class="alert alert-success rounded-sm" role="status">{{ session('status') }}</div>@endif
    <div class="rounded-sm border border-base-300 bg-base-100">
        @forelse($notifications as $notification)
            <form method="post" action="{{ route('notifications.open', $notification->id) }}">@csrf<button class="flex w-full items-start gap-3 border-b border-base-300 p-4 text-left hover:bg-base-200" type="submit"><x-lucide-bell class="mt-1 size-4 shrink-0 opacity-60" aria-hidden="true" /><span class="min-w-0 flex-1"><span class="flex flex-wrap items-center gap-2 text-sm font-semibold">{{ $notification->title }}@if($notification->resolved_at)<span class="badge badge-sm badge-ghost">Resolved</span>@elseif(!$notification->read_at)<span class="badge badge-sm badge-primary">Unread</span>@endif</span><span class="mt-1 block text-sm opacity-70">{{ $notification->message }}</span><span class="mt-2 block text-xs opacity-50">{{ $notification->created_at->format('d M Y H:i') }}</span></span><x-lucide-chevron-right class="mt-1 size-4 shrink-0" aria-hidden="true" /></button></form>
        @empty
            <div class="p-10 text-center"><x-lucide-bell class="mx-auto mb-3 size-8 opacity-40" aria-hidden="true" /><h2 class="font-semibold">No notifications yet</h2><p class="mt-2 text-sm opacity-60">Choose reminder and warning preferences in your budget's Notifications tab.</p><a class="btn btn-primary mt-4" href="{{ route('budgets.index') }}">Open budgets</a></div>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>
@endsection
