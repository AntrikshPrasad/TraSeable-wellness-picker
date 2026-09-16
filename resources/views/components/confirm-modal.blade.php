@props([
    'name',
    'title',
    'action',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
    'tone' => 'neutral',
    // Set this when the dialog's wording depends on Livewire state that changes
    // as it is opened - naming the row you are about to delete, say. Only safe
    // on a screen that does not poll; see the wire:ignore note below.
    'live' => false,
])

{{--
    An in-app replacement for wire:confirm, which can only ever produce the
    browser's native confirm() box - complete with the "127.0.0.1:8000 says"
    heading, and no way to style it.

    Open it from a button with:
        x-on:click="$dispatch('open-modal', 'your-name')"
--}}

{{--
    wire:ignore keeps Livewire's DOM patching away from the dialog. The picking
    screen polls every five seconds, and the server always renders this closed -
    without this, a poll landing while the dialog is open would patch it back to
    display:none and it would vanish mid-decision. Nothing in here needs to
    update live.
--}}
<div @unless ($live) wire:ignore @endunless wire:key="confirm-modal-{{ $name }}">
<x-modal :name="$name" max-width="md" focusable>
    <div class="p-6">
        <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>

        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $slot }}</p>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
            <button
                type="button"
                x-on:click="$dispatch('close')"
                class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
            >
                {{ $cancel }}
            </button>

            {{--
                Close first, then call the method. The modal adds
                overflow-y-hidden to <body> while it is open and removes it when
                show flips to false - if Livewire re-rendered this element away
                first, that class would be stranded and the page would be stuck
                unable to scroll.
            --}}
            <button
                type="button"
                x-on:click="$dispatch('close'); $wire.{{ $action }}()"
                @class([
                    'rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2',
                    'bg-gray-900 hover:bg-gray-700 focus:ring-gray-900' => $tone !== 'danger',
                    'bg-red-600 hover:bg-red-700 focus:ring-red-600' => $tone === 'danger',
                ])
            >
                {{ $confirm }}
            </button>
        </div>
    </div>
</x-modal>
</div>
