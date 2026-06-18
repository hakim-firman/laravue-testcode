<x-mail::message>
# You're on the list 🎉

Hi {{ $name }}, your spot for **{{ $eventName }}** is confirmed.

**Status:** {{ ucfirst($status) }}
**When:** {{ $when }}
@if ($location)
**Where:** {{ $location }}@if ($venue) · {{ $venue }}@endif
@endif

We'll remind you 3 days before and again the day before.

<x-mail::button :url="$url">
View event
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
