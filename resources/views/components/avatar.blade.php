@props(['user'])

@php
    // Nobody uploads a photo for an internal app, so initials on a colour keyed
    // to the user id: stable across sessions and readable at 24px.
    $palette = [
        'bg-rose-500', 'bg-orange-500', 'bg-amber-500', 'bg-emerald-500',
        'bg-teal-500', 'bg-sky-500', 'bg-indigo-500', 'bg-violet-500',
    ];

    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<span
    title="{{ $user->name }}"
    {{ $attributes->class([
        'inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full',
        'text-[10px] font-semibold text-white ring-2 ring-white',
        $palette[$user->id % count($palette)],
    ]) }}
>{{ $initials }}</span>
