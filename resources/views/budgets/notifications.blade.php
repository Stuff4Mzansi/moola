@if($errors->any())
<div class="alert alert-error mb-4 rounded-sm" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="grid gap-4 lg:grid-cols-2">
    <section class="card border border-base-300 bg-base-100 rounded-sm">
        <div class="card-body gap-4">
            <div><h3 class="card-title text-base">Your notifications</h3><p class="mt-1 text-sm opacity-60">Choose what you receive for {{ $budget->name }}. Your choices do not affect other members.</p></div>
            <form method="post" action="{{ route('notifications.preferences', $budget) }}" class="space-y-3">
                @csrf
                @foreach($notificationTypes as $type => $label)
                    <label class="flex items-center gap-3 text-sm"><input class="checkbox checkbox-sm" type="checkbox" name="enabled_types[]" value="{{ $type }}" @checked(in_array($type, old('enabled_types', array_values(array_diff(array_keys($notificationTypes), $mutedNotificationTypes))), true))><span>{{ $label }}</span></label>
                @endforeach
                <div class="border-t border-base-300 pt-3">
                    <input type="hidden" name="email_enabled" value="0">
                    <label class="flex items-center gap-3 text-sm"><input class="checkbox checkbox-sm" type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $emailEnabled)) @disabled(!$emailAvailable && !$emailEnabled)>Also send these notifications by email</label>
                    <p class="mt-2 text-xs opacity-60">@if($emailAvailable)Emails go to your account address. Only new notifications after you enable email are sent.@elseif($emailEnabled)Email delivery is paused until an admin enables it again. Your preference is saved.@else An admin needs to configure email delivery in Settings first.@endif</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 pt-2"><button class="btn btn-primary" type="submit">Save my preferences</button><a class="btn btn-ghost" href="{{ route('notifications.index') }}">Notification history</a></div>
            </form>
        </div>
    </section>
    <section class="card border border-base-300 bg-base-100 rounded-sm">
        <div class="card-body gap-4">
            <div><h3 class="card-title text-base">Budget alert rules</h3><p class="mt-1 text-sm opacity-60">Shared rules for this budget. Reminders use {{ config('app.timezone') }}.</p></div>
            @if($canEdit)
            <form method="post" action="{{ route('notifications.settings', $budget) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="notifications_enabled" value="0">
                <label class="flex items-center gap-3 text-sm"><input class="toggle toggle-sm" type="checkbox" name="notifications_enabled" value="1" @checked(old('notifications_enabled', $budget->notifications_enabled))>Enable budget notifications</label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label><span class="label">Warn when spending reaches (%)</span><input class="input w-full" type="number" name="notification_threshold" value="{{ old('notification_threshold', $budget->notification_threshold) }}" min="1" max="99" required></label>
                    <label><span class="label">Payment reminder (days before)</span><input class="input w-full" type="number" name="reminder_days" value="{{ old('reminder_days', $budget->reminder_days) }}" min="0" max="30" required></label>
                    <label><span class="label">Period ending (days before)</span><input class="input w-full" type="number" name="period_reminder_days" value="{{ old('period_reminder_days', $budget->period_reminder_days) }}" min="0" max="14" required></label>
                </div>
                <button class="btn btn-primary" type="submit">Save budget rules</button>
            </form>
            @else
            <p class="text-sm">{{ $budget->notifications_enabled ? 'Enabled' : 'Disabled' }} &middot; {{ $budget->notification_threshold }}% warning &middot; {{ $budget->reminder_days }} days before payments &middot; {{ $budget->period_reminder_days }} days before the period ends.</p>
            <p class="text-xs opacity-60">The owner or an editor can change these rules.</p>
            @endif
            <p class="text-xs opacity-60">Warnings fire once per limit and period. Overall warnings use expected income, or total allocations if no income is planned. Paid or resolved alerts leave the active list. Overdue reminders cover current periods and periods ended within the last seven days.</p>
        </div>
    </section>
</div>
