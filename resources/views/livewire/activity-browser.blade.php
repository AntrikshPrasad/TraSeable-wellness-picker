@use('App\Enums\Location')
@use('App\Livewire\ActivityBrowser')

@php
    $canPick = $this->pickingIsOpen();
    $remaining = $this->picksRemaining();

    $chip = fn (bool $on) => $on
        ? 'border-gray-900 bg-gray-900 text-white'
        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400';
@endphp

<div class="mx-auto max-w-2xl px-4 py-6 sm:px-6">

    <header class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">What can we do?</h1>
        <p class="mt-1 text-sm text-gray-500">
            Narrow it down by what you fancy, where you are, and how long you have got.
        </p>
    </header>

    <div class="space-y-4 rounded-2xl border border-gray-200 bg-white p-4">

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-gray-400">What kind of thing</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <button
                        type="button"
                        wire:click="toggleCategory({{ $category->id }})"
                        wire:key="cat-{{ $category->id }}"
                        class="flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium capitalize transition-colors {{ $chip(in_array($category->id, $categoryIds, true)) }}"
                    >
                        <span class="h-2 w-2 rounded-full" style="background-color: {{ $category->color }}"></span>
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Where</p>
            <div class="flex flex-wrap gap-2">
                {{-- Only indoor and outdoor are offered. "Either" is not a
                     question anyone asks - it is the answer an activity gives,
                     and those activities match whichever you choose. --}}
                @foreach ([Location::Indoor, Location::Outdoor] as $option)
                    <button
                        type="button"
                        wire:click="setLocation('{{ $option->value }}')"
                        wire:key="loc-{{ $option->value }}"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors {{ $chip($location === $option->value) }}"
                    >
                        {{ $option->label() }}
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Time available</p>
            <div class="flex flex-wrap gap-2">
                @foreach (ActivityBrowser::DURATIONS as $minutes)
                    <button
                        type="button"
                        wire:click="setMaxDuration('{{ $minutes }}')"
                        wire:key="dur-{{ $minutes }}"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors {{ $chip($maxDuration === (string) $minutes) }}"
                    >
                        {{ $minutes >= 120 ? '2 hrs' : $minutes.' min' }}
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-gray-400">How many of you</p>
            <div class="flex flex-wrap gap-2">
                @foreach (ActivityBrowser::GROUP_SIZES as $size)
                    <button
                        type="button"
                        wire:click="setGroupSize('{{ $size }}')"
                        wire:key="size-{{ $size }}"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors {{ $chip($groupSize === (string) $size) }}"
                    >
                        {{ $size }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($this->hasFilters())
            <button
                type="button"
                wire:click="clearFilters"
                class="text-xs text-gray-400 underline underline-offset-4 hover:text-gray-600"
            >
                Clear filters
            </button>
        @endif
    </div>

    <div class="mt-5 flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-gray-900">
            @if ($results->isEmpty())
                Nothing fits
            @else
                {{ $results->count() }} of {{ $totalActive }} {{ Str::plural('activity', $totalActive) }} {{ $results->count() === 1 ? 'fits' : 'fit' }}
            @endif
        </p>

        @if ($results->count() > 1)
            <button
                type="button"
                wire:click="surpriseMe"
                class="shrink-0 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:bg-gray-50"
            >
                {{ $suggestion ? 'Try another' : 'Surprise me' }}
            </button>
        @endif
    </div>

    @if ($suggestion)
        <div class="mt-3 rounded-2xl border-2 border-gray-900 bg-white p-5 text-center" wire:key="suggestion-{{ $suggestion->id }}">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">How about</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">{{ $suggestion->name }}</h2>

            <x-activity-meta :activity="$suggestion" show-both class="mt-2 justify-center" />

            @if ($suggestion->description)
                <p class="mx-auto mt-3 max-w-sm text-sm text-gray-600">{{ $suggestion->description }}</p>
            @endif

            {{-- A nudge, not a verdict. The wheel is what actually decides. --}}
            <p class="mt-4 text-xs text-gray-400">Picked at random from the {{ $results->count() }} that fit.</p>
        </div>
    @endif

    @if ($canPick)
        <p class="mt-4 text-xs text-gray-500">
            @if ($remaining === 0)
                All three of your picks are used. Tap one you have already picked to swap it out.
            @else
                Tap anything here to put it on this week's wheel &mdash;
                {{ $remaining }} {{ Str::plural('pick', $remaining) }} left.
            @endif
        </p>
    @endif

    <ul class="mt-3 divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-200 bg-white">
        @forelse ($results as $activity)
            @php
                $isPicked = $this->hasPicked($activity->id);
                $pickers = $pickersByActivity[$activity->id] ?? collect();
                $isDimmed = $canPick && ! $isPicked && $remaining === 0;
            @endphp

            <li wire:key="result-{{ $activity->id }}">
                @if ($canPick)
                    <button
                        type="button"
                        wire:click="toggle({{ $activity->id }})"
                        aria-pressed="{{ $isPicked ? 'true' : 'false' }}"
                        class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-gray-50 {{ $isDimmed ? 'opacity-50' : '' }}"
                    >
                        <x-pick-indicator :picked="$isPicked" />

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900">{{ $activity->name }}</span>
                            <x-activity-meta :activity="$activity" show-both class="mt-0.5" />
                        </span>

                        @if ($pickers->isNotEmpty())
                            <x-avatar-stack :users="$pickers" />
                        @endif
                    </button>
                @else
                    {{-- No open session, so there is nothing to tap. A plain row
                         rather than a button that would do nothing. --}}
                    <div class="flex items-center gap-3 p-4">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $activity->category->color }}"></span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900">{{ $activity->name }}</span>
                            <x-activity-meta :activity="$activity" show-both class="mt-0.5" />
                        </span>
                    </div>
                @endif

                @if ($blockedBy === $activity->id)
                    <p class="border-t border-amber-100 bg-amber-50 px-4 py-2.5 text-xs text-amber-800">
                        That is all three picks used. Tap one you have already picked to swap it out.
                    </p>
                @endif
            </li>
        @empty
            <li class="p-8 text-center">
                <p class="text-sm font-medium text-gray-900">Nothing matches all of that</p>
                <p class="mx-auto mt-1 max-w-xs text-sm text-gray-500">
                    Try dropping a filter &mdash; the time and group size ones are usually the strictest.
                </p>

                @if ($this->hasFilters())
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="mt-4 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-700"
                    >
                        Clear all filters
                    </button>
                @endif
            </li>
        @endforelse
    </ul>
</div>
