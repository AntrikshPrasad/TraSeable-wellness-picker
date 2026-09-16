@use('App\Enums\SessionStatus')

{{-- One route, four states - what the brief means by "same route, different
     state". Polling keeps other people's picks and avatars current during the
     Monday meeting; it stops once the session is decided, and while a spin is
     animating. --}}
<div
    class="mx-auto max-w-2xl px-4 py-6 sm:px-6"
    @if ($session?->isOpen() && ! $spinning) wire:poll.5s @endif
>
    @if (! $session)

        <div class="rounded-2xl border-2 border-dashed border-gray-300 bg-white p-8 text-center">
            <h1 class="text-lg font-semibold text-gray-900">No session open</h1>
            <p class="mx-auto mt-2 max-w-sm text-sm text-gray-500">
                Friday hasn't been set up yet. It normally starts on its own on Monday morning.
            </p>
            <button
                type="button"
                wire:click="startWeek"
                class="mt-5 inline-flex items-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
            >
                Start this week
            </button>
        </div>

    @elseif ($session->status === SessionStatus::Skipped)

        <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center">
            <h1 class="text-lg font-semibold text-gray-900">This week was skipped</h1>
            <p class="mt-2 text-sm text-gray-500">
                Nothing planned for {{ $session->meeting_date->format('l j F') }}.
            </p>

            {{-- The only way back from a skip. Picks were never deleted, so
                 reopening restores the wheel exactly as it was. --}}
            <button
                type="button"
                x-on:click="$dispatch('open-modal', 'confirm-reopen')"
                class="mt-5 text-sm font-medium text-gray-500 underline underline-offset-4 hover:text-gray-900"
            >
                Changed your mind? Reopen this week
            </button>
        </div>

        <x-confirm-modal
            name="confirm-reopen"
            title="Reopen this week?"
            action="resetWeek"
            confirm="Reopen it"
        >
            {{ $session->meeting_date->format('l j F') }} goes back to picking. Any picks
            made before it was skipped are still there.
        </x-confirm-modal>

        @if ($notice)
            <p class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-center text-xs text-amber-800">
                {{ $notice }}
            </p>
        @endif

    @elseif ($session->isDecided())

        @include('livewire.partials.result')

    @else

        @include('livewire.partials.picking')

    @endif
</div>
