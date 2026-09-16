<?php

namespace App\Livewire\Concerns;

use App\Models\Pick;
use App\Models\WellnessSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Tapping an activity to pick or un-pick it for the current week.
 *
 * Shared by the Monday picking screen and the browse screen so the three-pick
 * rule cannot end up enforced differently depending on where you tapped.
 */
trait PicksActivities
{
    /**
     * Activity ids the signed-in user has picked this session.
     *
     * This array is the single source of truth for the picking UI: the
     * selection ticks, the "N picks left" counter, the progress bar and the
     * dimmed rows are all derived from it rather than tracked separately.
     */
    public array $picks = [];

    /**
     * Activity the user tapped while out of picks.
     *
     * These rows must not be disabled - they stay tappable and explain
     * themselves, which needs somewhere to record which row was tapped.
     */
    public ?int $blockedBy = null;

    /**
     * Queried on each call rather than cached in a property. Livewire rebuilds
     * the component on every request, so a cached session would have to be
     * invalidated by hand after every write - and it is one indexed lookup.
     */
    protected function session(): ?WellnessSession
    {
        return WellnessSession::current();
    }

    protected function loadPicks(): array
    {
        $session = $this->session();

        if (! $session) {
            return [];
        }

        return $session->picks()
            ->where('user_id', auth()->id())
            ->pluck('activity_id')
            ->all();
    }

    public function picksRemaining(): int
    {
        return max(0, WellnessSession::PICKS_PER_USER - count($this->picks));
    }

    public function hasPicked(int $activityId): bool
    {
        return in_array($activityId, $this->picks, true);
    }

    /** Can this user pick anything at all right now? */
    public function pickingIsOpen(): bool
    {
        return (bool) $this->session()?->isOpen();
    }

    /**
     * Pick or un-pick an activity. The whole row calls this, so it has to cope
     * with being told to add something already added.
     */
    public function toggle(int $activityId): void
    {
        $session = $this->session();

        if (! $session?->isOpen()) {
            return;
        }

        $this->blockedBy = null;

        if ($this->hasPicked($activityId)) {
            $session->picks()
                ->where('user_id', auth()->id())
                ->where('activity_id', $activityId)
                ->delete();

            $this->picks = array_values(array_diff($this->picks, [$activityId]));

            return;
        }

        if ($this->picksRemaining() === 0) {
            $this->blockedBy = $activityId;

            return;
        }

        try {
            $session->picks()->create([
                'user_id' => auth()->id(),
                'activity_id' => $activityId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Double tap, or two tabs open. The unique index caught it; the row
            // is picked either way, so fall through and let the UI agree.
        }

        $this->picks[] = $activityId;
    }

    /** activity_id => Collection<User>, for the stacked avatars on each row. */
    protected function pickersByActivity(?WellnessSession $session): Collection
    {
        if (! $session) {
            return collect();
        }

        return $session->picks()
            ->with('user:id,name')
            ->get()
            ->groupBy('activity_id')
            ->map(fn (Collection $picks) => $picks->pluck('user'));
    }
}
