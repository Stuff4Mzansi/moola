<x-mail::message>
# {{ $heading }}

{{ $bodyText }}

<x-mail::button :url="$actionUrl">Open Moola</x-mail::button>

Manage email reminders in the Notifications tab of your budget.

{{ config('app.name') }}
</x-mail::message>
