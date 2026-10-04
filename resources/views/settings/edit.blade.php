@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <header><h1 class="text-2xl font-semibold tracking-tight">Settings</h1><p class="mt-1 text-sm text-base-content/60">Configure email delivery for your household.</p></header>
    @if(session('status'))<div class="alert alert-success rounded-sm" role="status">{{ session('status') }}</div>@endif
    @if(session('mail_error'))<div class="alert alert-error rounded-sm" role="alert">{{ session('mail_error') }}</div>@endif
    @if($errors->any())<div class="alert alert-error rounded-sm" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <section class="card rounded-sm border border-base-300 bg-base-100">
            <div class="card-body gap-5">
                <div class="flex items-start gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-sm bg-primary/10 text-primary"><x-lucide-mail class="size-4" aria-hidden="true" /></span><div><h2 class="text-base font-semibold">Email &amp; SMTP</h2><p class="mt-1 text-xs text-base-content/60">Use your email provider or a local SMTP relay.</p></div></div>
                <form method="post" action="{{ route('settings.mail.update') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="enabled" value="0">
                    <label class="flex items-center gap-3 text-sm font-medium"><input class="toggle toggle-sm" type="checkbox" name="enabled" value="1" @checked(old('enabled', $mailSettings->enabled))>Enable email notifications</label>
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_7rem]">
                        <label><span class="label">SMTP host</span><input class="input w-full" name="host" value="{{ old('host', $mailSettings->host) }}" placeholder="smtp.example.com" maxlength="255" autocomplete="off"></label>
                        <label><span class="label">Port</span><input class="input w-full" name="port" type="number" min="1" max="65535" value="{{ old('port', $mailSettings->port) }}" required></label>
                    </div>
                    <label class="block"><span class="label">Connection security</span><select class="select w-full" name="security">@foreach(['starttls' => 'STARTTLS (usually port 587)', 'smtps' => 'TLS / SSL (usually port 465)', 'none' => 'None (internal relay only)'] as $value => $label)<option value="{{ $value }}" @selected(old('security', $mailSettings->security) === $value)>{{ $label }}</option>@endforeach</select></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label><span class="label">Username (optional)</span><input class="input w-full" name="username" value="{{ old('username', $mailSettings->username) }}" maxlength="255" autocomplete="off"></label>
                        <label><span class="label">Password or app password</span><input class="input w-full" type="password" name="password" maxlength="1024" autocomplete="new-password" placeholder="{{ $mailSettings->getRawOriginal('password') ? 'Saved; leave blank to keep' : 'Enter SMTP password' }}"></label>
                    </div>
                    @if($mailSettings->getRawOriginal('password'))<label class="flex items-center gap-2 text-xs text-base-content/60"><input class="checkbox checkbox-xs" type="checkbox" name="clear_password" value="1">Remove saved password</label>@endif
                    <p class="text-xs text-base-content/60">Passwords are stored encrypted. Your provider may require an app password.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label><span class="label">Sender email address</span><input class="input w-full" type="email" name="from_address" value="{{ old('from_address', $mailSettings->from_address) }}" maxlength="255" placeholder="reminders@example.com"></label>
                        <label><span class="label">Sender name</span><input class="input w-full" name="from_name" value="{{ old('from_name', $mailSettings->from_name) }}" maxlength="255" required></label>
                    </div>
                    <div class="flex items-center justify-between border-t border-base-300 pt-4"><p class="text-xs text-base-content/50">Changes apply without restarting the app.</p><button class="btn btn-primary" type="submit">Save email settings</button></div>
                </form>
            </div>
        </section>
        <div class="space-y-4">
            <section class="card rounded-sm border border-base-300 bg-base-100"><div class="card-body gap-4"><div class="flex items-center justify-between"><h2 class="text-sm font-semibold">Delivery check</h2><span class="badge badge-sm {{ $mailSettings->enabled ? 'badge-primary' : 'badge-ghost' }}">{{ $mailSettings->enabled ? 'Enabled' : 'Disabled' }}</span></div><p class="text-xs leading-relaxed text-base-content/60">Save your settings, then send a test to <span class="font-medium text-base-content">{{ auth()->user()->email }}</span>.</p><form method="post" action="{{ route('settings.mail.test') }}">@csrf<button class="btn btn-outline w-full" type="submit" @disabled(!$mailSettings->enabled)><x-lucide-send class="size-3.5" aria-hidden="true" />Send test email</button></form>@if($mailSettings->last_test_at)<p class="text-xs {{ $mailSettings->last_test_success ? 'text-success' : 'text-error' }}">Last test {{ $mailSettings->last_test_success ? 'sent successfully' : 'failed' }} &middot; {{ $mailSettings->last_test_at->diffForHumans() }}</p>@endif @if($mailSettings->last_delivery_at)<p class="text-xs text-base-content/50">Last reminder sent {{ $mailSettings->last_delivery_at->diffForHumans() }}.</p>@endif @if($failedEmails > 0)<p class="text-xs text-warning">{{ $failedEmails }} reminder(s) awaiting delivery after a failure. Retries run automatically, up to five attempts. Saving corrected settings allows another retry.</p>@endif</div></section>
            <section class="rounded-sm border border-base-300 bg-base-100/60 p-4"><h2 class="text-sm font-semibold">Member preferences</h2><p class="mt-2 text-xs leading-relaxed text-base-content/60">Members choose email delivery in Budget &rarr; Notifications. Their selected reminder types apply to both the bell and email. Existing notifications are not emailed when a member first opts in.</p><p class="mt-3 text-xs leading-relaxed text-base-content/60">The scheduler checks reminders every minute. For links in emails to work outside this server, set APP_URL to your app's address.</p></section>
        </div>
    </div>
</div>
@endsection
