<x-mail::message>
# {{ $heading }}

@foreach ($groups as $label => $tasks)
**{{ $label }}**

@foreach ($tasks as $task)
- [{{ $task['title'] }}]({{ $task['url'] }})<br>{{ $task['where'] }}@if ($task['dates']) · {{ $task['dates'] }}@endif

@endforeach

@endforeach
<x-mail::button :url="route('dashboard')">
{{ __('Open overview') }}
</x-mail::button>

<small>{{ __('You get this email because you subscribed to it.') }} [{{ __('Change email settings') }}]({{ route('digests.edit') }})</small>
</x-mail::message>
