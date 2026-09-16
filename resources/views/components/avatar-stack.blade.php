@props(['users'])

{{-- Overlapping avatars read as "these people", where a bare number reads as a
     score. The brief wants the former. --}}
<span class="flex shrink-0 -space-x-2">
    @foreach ($users->take(4) as $user)
        <x-avatar :user="$user" />
    @endforeach

    @if ($users->count() > 4)
        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-300 text-[10px] font-semibold text-gray-700 ring-2 ring-white">
            +{{ $users->count() - 4 }}
        </span>
    @endif
</span>
