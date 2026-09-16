// Alpine is deliberately not imported or started here. Livewire ships its own
// copy and starts it; importing a second one gives you "Detected multiple
// instances of Alpine running" and directives that silently bind to the wrong
// instance. Register components on alpine:init instead.
document.addEventListener('alpine:init', () => {
    /**
     * Drives the wheel.
     *
     * It asks the server for a winner, then animates to the angle the server
     * sent back. It never picks anything itself - all it knows how to do is
     * rotate to a number it was given.
     */
    Alpine.data('wheel', (config = {}) => ({
        durationMs: config.durationMs ?? 4200,
        rotation: 0,
        spinning: false,
        error: null,
        // Used by the rained-off card, which keeps its wheel folded away until
        // someone actually looks out of the window.
        open: config.open ?? false,

        async spin(url, force = false) {
            if (this.spinning) {
                return;
            }

            this.error = null;
            this.spinning = true;

            // Tell the server component we are mid-spin so it stops polling.
            // A poll landing during the animation would re-render the wheel out
            // from under the transition and make it jump.
            await this.$wire.set('spinning', true);

            let payload;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ force }),
                });

                payload = await response.json();

                if (! response.ok) {
                    return this.stop(payload.message ?? 'The spin was refused.');
                }
            } catch {
                return this.stop('Could not reach the server. Check your connection and try again.');
            }

            await this.landOn(payload.winning_angle);

            // Clearing the flag doubles as the re-render: the session is decided
            // now, so the component comes back showing the result. Leaving
            // spinning set would also leave polling switched off server-side.
            this.spinning = false;
            this.$wire.set('spinning', false);
        },

        /**
         * Rotate so the given angle ends up under the pointer at twelve o'clock.
         *
         * Slice 0 starts at twelve and slices run clockwise, so bringing an
         * angle to the top means rotating the wheel backwards by it. The extra
         * whole turns are just for show, and the rotation only ever increases
         * so a second spin carries on forwards rather than unwinding.
         */
        landOn(angle) {
            const flourish = 6 * 360;
            const wholeTurnsSoFar = Math.ceil(this.rotation / 360) * 360;

            this.rotation = wholeTurnsSoFar + flourish - angle;

            return new Promise((resolve) => setTimeout(resolve, this.durationMs));
        },

        stop(message) {
            this.error = message;
            this.spinning = false;
            this.$wire.set('spinning', false);
        },
    }));
});
