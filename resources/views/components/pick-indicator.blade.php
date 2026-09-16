@props(['picked' => false])

{{-- The tick/empty-circle pair used wherever an activity can be picked. --}}
@if ($picked)
    <svg {{ $attributes->class(['h-6 w-6 shrink-0 text-emerald-600']) }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm4.28 7.53-5.25 5.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 1 1 1.06-1.06l1.72 1.72 4.72-4.72a.75.75 0 1 1 1.06 1.06Z" clip-rule="evenodd" />
    </svg>
@else
    <span {{ $attributes->class(['h-6 w-6 shrink-0 rounded-full border-2 border-gray-300']) }}></span>
@endif
