@use('App\Enums\Location')

<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">

    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Activities</h1>
            <p class="mt-1 text-sm text-gray-500">
                The pool the wheel and the browser both draw from.
            </p>
        </div>

        <button
            type="button"
            wire:click="startAdding"
            class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-700"
        >
            Add an activity
        </button>
    </header>

    @if ($notice)
        <p class="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ $notice }}
        </p>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="mb-6 space-y-4 rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="text-base font-semibold text-gray-900">
                {{ $editingId ? 'Edit activity' : 'New activity' }}
            </h2>

            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" wire:model="name" type="text" class="mt-1 block w-full" placeholder="Crazy golf" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="categoryId" value="Category" />
                    <select id="categoryId" wire:model="categoryId" class="mt-1 block w-full rounded-md border-gray-300 capitalize shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Choose...</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('categoryId')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="location" value="Location" />
                    <select id="location" wire:model="location" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Choose...</option>
                        @foreach (Location::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="durationMinutes" value="Duration in minutes" />
                    <x-text-input id="durationMinutes" wire:model="durationMinutes" type="number" min="5" max="600" class="mt-1 block w-full" placeholder="Optional" />
                    <x-input-error :messages="$errors->get('durationMinutes')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="minPeople" value="People needed" />
                    <x-text-input id="minPeople" wire:model="minPeople" type="number" min="1" max="100" class="mt-1 block w-full" placeholder="Optional" />
                    <x-input-error :messages="$errors->get('minPeople')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional"></textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
            </div>

            <p class="text-xs text-gray-500">
                Leaving duration or people blank means "unspecified" &mdash; the browser's
                time and group filters keep those rather than hiding them.
            </p>

            <div class="flex gap-2">
                <x-primary-button type="submit">{{ $editingId ? 'Save changes' : 'Add it' }}</x-primary-button>
                <x-secondary-button type="button" wire:click="cancelForm">Cancel</x-secondary-button>
            </div>
        </form>
    @endif

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <x-text-input
            wire:model.live.debounce.300ms="search"
            type="search"
            class="w-full sm:w-64"
            placeholder="Search by name"
            aria-label="Search activities by name"
        />

        @if ($retiredCount > 0)
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" wire:model.live="showRetired" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                Show retired ({{ $retiredCount }})
            </label>
        @endif
    </div>

    {{-- A real table, scrolled sideways on a phone rather than reflowed. This
         is the one screen people use at a desk. --}}
    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white">
        <table class="w-full min-w-[46rem] text-left text-sm">
            <thead class="border-b border-gray-200 text-xs uppercase tracking-widest text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold">Activity</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Category</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Where</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Mins</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">People</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Picked</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Won</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse ($activities as $activity)
                    <tr wire:key="row-{{ $activity->id }}" class="{{ $activity->is_active ? '' : 'bg-gray-50 text-gray-400' }}">
                        <td class="px-4 py-3">
                            <span class="font-medium {{ $activity->is_active ? 'text-gray-900' : 'text-gray-400' }}">
                                {{ $activity->name }}
                            </span>
                            @unless ($activity->is_active)
                                <span class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600">Retired</span>
                            @endunless
                            @if ($activity->description)
                                <span class="mt-0.5 block max-w-xs truncate text-xs text-gray-400">{{ $activity->description }}</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 capitalize">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $activity->category->color }}"></span>
                                {{ $activity->category->name }}
                            </span>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">{{ $activity->location->label() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $activity->duration_minutes ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $activity->min_people ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $activity->picks_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $activity->decided_sessions_count }}</td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3 whitespace-nowrap">
                                <button type="button" wire:click="edit({{ $activity->id }})" class="font-medium text-gray-700 underline underline-offset-4 hover:text-gray-900">
                                    Edit
                                </button>

                                @if ($activity->is_active)
                                    <button type="button" wire:click="retire({{ $activity->id }})" class="font-medium text-gray-500 underline underline-offset-4 hover:text-gray-900">
                                        Retire
                                    </button>
                                @else
                                    <button type="button" wire:click="restore({{ $activity->id }})" class="font-medium text-emerald-700 underline underline-offset-4 hover:text-emerald-900">
                                        Restore
                                    </button>
                                @endif

                                @if ($activity->canBeErased())
                                    <button
                                        type="button"
                                        wire:click="confirmErase({{ $activity->id }})"
                                        x-on:click="$dispatch('open-modal', 'confirm-erase')"
                                        class="font-medium text-red-600 underline underline-offset-4 hover:text-red-800"
                                    >
                                        Delete
                                    </button>
                                @else
                                    {{-- Deleting would cascade away real picks and blank out a
                                         past Friday's result, so it is not offered once an
                                         activity has a history. Retiring does the same job
                                         without rewriting what happened. --}}
                                    <span class="cursor-help text-gray-300" title="Has been picked before, so it can only be retired">
                                        Delete
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $search !== '' ? 'Nothing matches that search' : 'No activities yet' }}
                            </p>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $search !== '' ? 'Try a shorter search, or check the retired ones.' : 'Add the first one to get the wheel started.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-confirm-modal
        name="confirm-erase"
        title="Delete this activity?"
        action="erase"
        confirm="Delete it"
        tone="danger"
        live
    >
        {{ $erasing?->name ?? 'This activity' }} has never been picked, so deleting it
        loses nothing. This cannot be undone &mdash; if you might want it back later,
        retire it instead.
    </x-confirm-modal>
</div>
