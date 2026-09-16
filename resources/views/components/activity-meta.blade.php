@props(['activity', 'showBoth' => false])

{{-- The sub-line under an activity name. The picking list shows duration OR
     people to keep rows to one line; the browse results and the result screen
     show both, because there the detail is the whole point. --}}
<span {{ $attributes->class(['flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500']) }}>
    <x-location-badge :location="$activity->location" />

    @if ($activity->duration_minutes)
        <span aria-hidden="true">&middot;</span>
        <span>{{ $activity->duration_minutes }} min</span>
    @endif

    @if ($activity->min_people && ($showBoth || ! $activity->duration_minutes))
        <span aria-hidden="true">&middot;</span>
        <span>{{ $activity->min_people }}+ people</span>
    @endif
</span>
