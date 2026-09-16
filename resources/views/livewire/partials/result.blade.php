{{-- Screen 2. This is up from Monday afternoon until Friday, so it is the
     screen people see most - it earns the space it takes. --}}

@php
    $winner = $session->activity;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 text-center sm:p-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">
        {{ $session->meeting_date->format('l j F') }}
    </p>

    <h1 class="mt-2 text-4xl font-bold leading-tight tracking-tight text-gray-900 sm:text-5xl">
        {{ $winner?->name ?? 'Decided' }}
    </h1>

    @if ($winner)
        <x-activity-meta
            :activity="$winner"
            show-both
            class="mt-3 justify-center !text-sm"
        />

        @if ($winner->description)
            <p class="mx-auto mt-4 max-w-sm text-sm text-gray-600">{{ $winner->description }}</p>
        @endif
    @endif

    @if ($winnerPickers->isNotEmpty())
        <div class="mt-6 flex flex-col items-center gap-2">
            <x-avatar-stack :users="$winnerPickers" />
            <p class="text-xs text-gray-500">
                Picked by {{ $winnerPickers->pluck('name')->join(', ', ' and ') }}
            </p>
        </div>
    @endif

    @if ($session->wasRainedOff())
        <p class="mt-6 border-t border-gray-100 pt-4 text-xs text-gray-500">
            Re-spun indoors on {{ $session->rained_off_at->format('j M') }} after the original pick was rained off.
        </p>
    @endif
</div>

@if ($runnersUp->isNotEmpty())
    <div class="mt-6">
        <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-widest text-gray-400">
            Also on the wheel
        </h2>

        {{-- Ordered by pick count here, unlike the picking list. Sorting by
             popularity while people are still choosing would feed back into what
             they choose; once the wheel has been spun there is nothing left to
             influence. --}}
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-200 bg-white">
            @foreach ($runnersUp as $slice)
                <li class="flex items-center gap-3 px-4 py-3" wire:key="runner-up-{{ $slice->activity->id }}">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $slice->activity->category->color }}"></span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-gray-700">{{ $slice->activity->name }}</span>
                        <span class="text-xs text-gray-400">
                            {{ $slice->weight }} {{ Str::plural('pick', $slice->weight) }}
                        </span>
                    </span>

                    @if (($pickersByActivity[$slice->activity->id] ?? collect())->isNotEmpty())
                        <x-avatar-stack :users="$pickersByActivity[$slice->activity->id]" />
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($notice)
    <p class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-center text-xs text-amber-800">
        {{ $notice }}
    </p>
@endif

@if ($rainWheel && ! $rainWheel->isEmpty())
    {{-- Distinct wire:key so Livewire never morphs the main wheel card into
         this one and carries its Alpine state across. --}}
    <div class="mt-6" wire:key="rain-wheel" x-data="wheel()">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <button
                type="button"
                x-on:click="open = true"
                x-show="! open"
                class="w-full text-sm font-medium text-gray-500 underline underline-offset-4 hover:text-gray-900"
            >
                Rained off? Re-spin indoors
            </button>

            <div x-show="open" x-cloak>
                <h2 class="mb-1 text-center text-sm font-semibold text-gray-900">Indoor re-spin</h2>
                <p class="mb-4 text-center text-xs text-gray-500">
                    Same picks, minus anything that needs the weather to hold.
                </p>

                <x-wheel :slices="$rainWheel->slices" />

                <button
                    type="button"
                    x-on:click="spin('{{ route('sessions.rained-off', $session) }}')"
                    x-bind:disabled="spinning"
                    class="mt-5 w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60"
                >
                    <span x-show="! spinning">Spin again</span>
                    <span x-show="spinning" x-cloak>Spinning…</span>
                </button>

                <p x-show="error" x-cloak x-text="error" class="mt-2 text-center text-xs text-red-600"></p>
            </div>
        </div>
    </div>
@endif

{{-- Undoes the spin but not the picking, so the wheel is unchanged and can be
     spun again. Quiet styling on purpose: it throws away a decision the team
     has already been told about. --}}
<div class="mt-6 text-center">
    <button
        type="button"
        x-on:click="$dispatch('open-modal', 'confirm-reset')"
        class="text-xs text-gray-400 underline underline-offset-4 hover:text-gray-600"
    >
        Reset this week's spin
    </button>
</div>

<x-confirm-modal
    name="confirm-reset"
    title="Reset this week's spin?"
    action="resetWeek"
    confirm="Reset the spin"
    tone="danger"
>
    {{ $session->activity?->name ?? 'The result' }} stops being this week's plan, and the
    week goes back to picking. Everyone's picks stay exactly as they are, so the
    wheel is unchanged and ready to spin again.
</x-confirm-modal>
