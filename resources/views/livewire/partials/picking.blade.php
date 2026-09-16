{{-- @use does not carry into an included partial, so the imports this file
     needs have to be declared here rather than in the parent view. --}}
@use('App\Enums\Location')
@use('App\Models\WellnessSession')

    {{-- Block form, not the inline parenthesised form: that one mis-parses a
         nested call like picksRemaining() and silently swallows the rest of
         the template. --}}
    @php
        $remaining = $this->picksRemaining();
    @endphp

    <header class="mb-5">
        <p class="text-sm font-medium text-gray-500">{{ $session->meeting_date->format('l') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">
            {{ $session->meeting_date->format('j F') }}
        </h1>

        {{-- "1 pick left" rather than "2 of 3 used": the number people act on
             is the one they have, not the one they have spent. --}}
        <p class="mt-4 text-sm font-semibold {{ $remaining === 0 ? 'text-gray-500' : 'text-gray-900' }}">
            @if ($remaining === 0)
                All three picks used
            @else
                {{ $remaining }} {{ Str::plural('pick', $remaining) }} left
            @endif
        </p>

        <div class="mt-2 flex gap-1.5" aria-hidden="true">
            @for ($segment = 0; $segment < WellnessSession::PICKS_PER_USER; $segment++)
                <span class="h-1.5 flex-1 rounded-full transition-colors {{ $segment < count($picks) ? 'bg-emerald-500' : 'bg-gray-200' }}"></span>
            @endfor
        </div>
    </header>

    {{-- Chips scroll sideways rather than wrapping, so the list below keeps
         a predictable starting position on a phone. --}}
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6">
        <button
            type="button"
            wire:click="filterBy(null)"
            class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors {{ $categoryFilter === null ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400' }}"
        >
            All
        </button>

        @foreach ($categories as $category)
            <button
                type="button"
                wire:click="filterBy({{ $category->id }})"
                class="flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium capitalize transition-colors {{ $categoryFilter === $category->id ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400' }}"
            >
                <span class="h-2 w-2 rounded-full" style="background-color: {{ $category->color }}"></span>
                {{ $category->name }}
            </button>
        @endforeach
    </div>

    <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-200 bg-white">
        @forelse ($activities as $activity)
            @php
                $isPicked = $this->hasPicked($activity->id);
                $pickers = $pickersByActivity[$activity->id] ?? collect();
                // Dimmed, never disabled: the row still responds, it just
                // explains why nothing happened.
                $isDimmed = ! $isPicked && $remaining === 0;
            @endphp

            <li wire:key="activity-{{ $activity->id }}">
                <button
                    type="button"
                    wire:click="toggle({{ $activity->id }})"
                    aria-pressed="{{ $isPicked ? 'true' : 'false' }}"
                    class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-gray-50 {{ $isDimmed ? 'opacity-50' : '' }}"
                >
                    <x-pick-indicator :picked="$isPicked" />

                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-gray-900">{{ $activity->name }}</span>

                        <x-activity-meta :activity="$activity" class="mt-0.5" />
                    </span>

                    @if ($pickers->isNotEmpty())
                        <x-avatar-stack :users="$pickers" />
                    @elseif ($activity->isNew())
                        {{-- A "New" badge instead of a bare zero: a count of
                             nought reads as a rejection rather than a debut. --}}
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">New</span>
                    @endif
                </button>

                @if ($blockedBy === $activity->id)
                    <p class="border-t border-amber-100 bg-amber-50 px-4 py-2.5 text-xs text-amber-800">
                        That is all three picks used. Tap one you have already picked to swap it out.
                    </p>
                @endif
            </li>
        @empty
            <li class="p-8 text-center text-sm text-gray-500">
                No activities in this category yet.
            </li>
        @endforelse
    </ul>

    <div class="mt-4">
        @if (! $showAddForm)
            <button
                type="button"
                wire:click="$set('showAddForm', true)"
                class="w-full rounded-2xl border-2 border-dashed border-gray-300 px-4 py-3 text-sm font-medium text-gray-600 transition hover:border-gray-400 hover:text-gray-900"
            >
                + Add an activity
            </button>
        @else
            <form wire:submit="addActivity" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
                <div>
                    <x-input-label for="newName" value="Name" />
                    <x-text-input id="newName" wire:model="newName" type="text" class="mt-1 block w-full" placeholder="Crazy golf" />
                    <x-input-error :messages="$errors->get('newName')" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="newCategoryId" value="Category" />
                        <select id="newCategoryId" wire:model="newCategoryId" class="mt-1 block w-full rounded-md border-gray-300 capitalize shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Choose...</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('newCategoryId')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="newLocation" value="Location" />
                        <select id="newLocation" wire:model="newLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Choose...</option>
                            @foreach (Location::cases() as $location)
                                <option value="{{ $location->value }}">{{ $location->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('newLocation')" class="mt-1" />
                    </div>
                </div>

                <p class="text-xs text-gray-500">Adding an activity uses one of your three picks.</p>

                <div class="flex gap-2">
                    <x-primary-button type="submit">Add and pick it</x-primary-button>
                    <x-secondary-button type="button" wire:click="$set('showAddForm', false)">Cancel</x-secondary-button>
                </div>
            </form>
        @endif
    </div>

    @if (! $wheel->isEmpty())
        {{-- The wheel keeps re-rendering with the polling above, so it stays
             in step as people pick. It is safe from being patched mid-spin
             because starting a spin sets $spinning, which stops the poll. --}}
        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5" wire:key="main-wheel" x-data="wheel()">
            <h2 class="mb-4 text-center text-sm font-semibold text-gray-900">
                {{ $wheel->totalWeight() }} {{ Str::plural('pick', $wheel->totalWeight()) }} on the wheel
            </h2>

            <x-wheel :slices="$wheel->slices" />

            <div class="mt-5">
                @if ($everyoneHasPicked)
                    <button
                        type="button"
                        x-on:click="spin('{{ route('sessions.spin', $session) }}')"
                        x-bind:disabled="spinning"
                        class="w-full rounded-xl bg-gray-900 px-4 py-3.5 text-base font-semibold text-white transition hover:bg-gray-700 disabled:opacity-60"
                    >
                        <span x-show="! spinning">Spin the wheel</span>
                        <span x-show="spinning" x-cloak>Spinning…</span>
                    </button>
                @else
                    {{-- Genuinely disabled here, because waiting is the right
                         default. The override below is the way past it. --}}
                    <button
                        type="button"
                        disabled
                        class="w-full cursor-not-allowed rounded-xl bg-gray-200 px-4 py-3.5 text-base font-semibold text-gray-500"
                    >
                        Waiting for {{ $usersYetToPick->pluck('name')->join(', ', ' and ') }}
                    </button>

                    <p class="mt-3 text-center text-xs text-gray-500">
                        Someone away?
                        <button
                            type="button"
                            x-on:click="spin('{{ route('sessions.spin', $session) }}', true)"
                            x-bind:disabled="spinning"
                            class="font-medium text-gray-700 underline underline-offset-4 hover:text-gray-900 disabled:opacity-50"
                        >
                            <span x-show="! spinning">Spin anyway</span>
                            <span x-show="spinning" x-cloak>Spinning…</span>
                        </button>
                    </p>
                @endif

                <p x-show="error" x-cloak x-text="error" class="mt-3 text-center text-xs text-red-600"></p>
            </div>
        </div>
    @endif

    <div class="mt-6 text-center">
        <button
            type="button"
            x-on:click="$dispatch('open-modal', 'confirm-skip')"
            class="text-xs text-gray-400 underline underline-offset-4 hover:text-gray-600"
        >
            Skip this week
        </button>
    </div>

    <x-confirm-modal
        name="confirm-skip"
        title="Skip this week?"
        action="skipWeek"
        confirm="Skip the week"
        tone="danger"
    >
        Nothing gets planned for {{ $session->meeting_date->format('l j F') }}. Picks are
        kept, so the week can be reopened afterwards if the team changes its mind.
    </x-confirm-modal>
