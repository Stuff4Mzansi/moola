<details class="dropdown dropdown-end static sm:relative">
    <summary class="btn btn-sm btn-ghost btn-square relative size-8 rounded-sm text-base-content/60 hover:bg-base-200 hover:text-base-content" aria-label="Notifications, {{ $notificationCount }} unread">
        <x-lucide-bell class="size-4" aria-hidden="true" />
        @if($notificationCount > 0)<span class="badge badge-primary badge-xs absolute -right-1 -top-1" aria-hidden="true">{{ $notificationCount > 99 ? '99+' : $notificationCount }}</span>@endif
    </summary>
    <div class="dropdown-content left-3 right-3 top-full z-40 mt-3 w-auto max-w-none sm:left-auto sm:right-0 sm:top-auto sm:w-80 sm:max-w-[calc(100vw-2rem)] rounded-sm border border-base-300 bg-base-100 shadow-xl">
        <div class="flex items-center justify-between border-b border-base-300 p-3"><h2 class="text-sm font-semibold">Notifications</h2><span class="text-xs opacity-60">{{ $notificationCount }} unread</span></div>
        <div class="max-h-80 overflow-y-auto">
            @forelse($recentNotifications as $notification)
                <form method="post" action="{{ route('notifications.open', $notification->id) }}">@csrf<button class="block w-full border-b border-base-300 p-3 text-left hover:bg-base-200" type="submit"><span class="flex items-start gap-2 text-sm font-medium">@if(!$notification->read_at)<span class="mt-1 size-2 shrink-0 rounded-sm bg-primary" aria-label="Unread"></span>@endif{{ $notification->title }}</span><span class="mt-1 block text-xs opacity-70">{{ $notification->message }}</span><span class="mt-1 block text-xs opacity-50">{{ $notification->created_at->diffForHumans() }}</span></button></form>
            @empty
                <div class="p-6 text-center"><x-lucide-bell class="mx-auto mb-2 size-6 opacity-40" aria-hidden="true" /><p class="text-sm">You're all caught up</p><p class="mt-1 text-xs opacity-60">Budget reminders and warnings appear here.</p></div>
            @endforelse
        </div>
        <div class="flex items-center justify-between p-2"><a class="btn btn-ghost" href="{{ route('notifications.index') }}">View all</a>@if($notificationCount > 0)<form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-ghost" type="submit">Mark all read</button></form>@endif</div>
    </div>
</details>
