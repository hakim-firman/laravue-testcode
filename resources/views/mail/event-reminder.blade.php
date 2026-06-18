<x-mail::message>
# Your event {{ $lead }}

Hi {{ $name }}, a quick reminder that **{{ $eventName }}** {{ $lead }}.

**When:** {{ $when }}
@if ($location)
**Where:** {{ $location }}@if ($venue) · {{ $venue }}@endif
@endif

<x-mail::button :url="$url">
View event
</x-mail::button>

See you there,<br>
{{ config('app.name') }}
</x-mail::message>
