<?php

namespace App\Livewire;

use App\Enums\Location;
use App\Livewire\Concerns\PicksActivities;
use App\Models\Activity;
use App\Models\Category;
use App\Models\WellnessSession;
use App\Support\Wheel;
use App\Support\WheelSlice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ActivityPicker extends Component
{
    use PicksActivities;

    /** Selected category chip. Null is "All". */
    public ?int $categoryFilter = null;

    public bool $showAddForm = false;

    /**
     * True from the moment the browser asks for a winner until the animation
     * finishes. It exists to switch off wire:poll for the duration - a poll
     * landing mid-spin re-renders the wheel out from under the CSS transition
     * and makes it jump.
     */
    public bool $spinning = false;

    /** Explains why an action did nothing. Cleared when another is attempted. */
    public ?string $notice = null;

    public string $newName = '';

    public ?int $newCategoryId = null;

    public string $newLocation = '';

    protected function rules(): array
    {
        return [
            // Unique for the same reason the manage screen enforces it: on the
            // wheel all anyone sees is the name, so two of them are
            // indistinguishable.
            'newName' => ['required', 'string', 'max:255', Rule::unique('activities', 'name')],
            'newCategoryId' => ['required', 'integer', 'exists:categories,id'],
            'newLocation' => ['required', Rule::enum(Location::class)],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newName' => 'name',
            'newCategoryId' => 'category',
            'newLocation' => 'location',
        ];
    }

    public function mount(): void
    {
        $this->picks = $this->loadPicks();
    }

    public function filterBy(?int $categoryId): void
    {
        $this->categoryFilter = $categoryId;
        $this->blockedBy = null;
    }

    /** Adding an activity spends one of the adder's three picks. */
    public function addActivity(): void
    {
        $session = $this->session();

        if (! $session?->isOpen()) {
            return;
        }

        if ($this->picksRemaining() === 0) {
            $this->addError('newName', 'Adding an activity uses a pick, and you have none left. Un-pick something first.');

            return;
        }

        $validated = $this->validate();

        $activity = Activity::create([
            'name' => $validated['newName'],
            'category_id' => $validated['newCategoryId'],
            'location' => $validated['newLocation'],
            'created_by' => auth()->id(),
        ]);

        $session->picks()->create([
            'user_id' => auth()->id(),
            'activity_id' => $activity->id,
        ]);

        $this->picks[] = $activity->id;

        $this->reset('newName', 'newCategoryId', 'newLocation', 'showAddForm');
    }

    /**
     * Manual fallback for when the scheduled Monday command has not run.
     * A double click is harmless - see WellnessSession::openForComingFriday().
     */
    public function startWeek(): void
    {
        WellnessSession::openForComingFriday();

        $this->picks = $this->loadPicks();
    }

    /**
     * Call the week off. Shares WellnessSession::skip() with the HTTP endpoint
     * so the rule lives in one place, whichever way it is reached.
     */
    public function skipWeek(): void
    {
        $this->session()?->skip();

        $this->picks = [];
    }

    /**
     * Throw away this week's result and go back to picking. Everyone's picks
     * survive, so the wheel is exactly as it was and can simply be spun again.
     */
    public function resetWeek(): void
    {
        $this->notice = null;
        $session = $this->session();

        if (! $session || $session->isOpen()) {
            return;
        }

        if ($session->anotherWeekIsOpen()) {
            $this->notice = 'Another week is already open for picking. Decide or skip that one first.';

            return;
        }

        if (! $session->reopen()) {
            $this->notice = 'Could not reset this week. Someone may have started another one.';

            return;
        }

        // The picks were never touched, so reload this user's from the database
        // rather than assuming what they were.
        $this->picks = $this->loadPicks();
    }

    protected function activities(): Collection
    {
        return Activity::query()
            ->active()
            ->with('category')
            ->when($this->categoryFilter, fn ($query, $id) => $query->where('category_id', $id))
            // Stable alphabetical order, deliberately NOT by pick count. Sorting
            // by popularity puts the most-picked activities at the top, where
            // they get picked because they are at the top - a feedback loop that
            // would quietly defeat the whole point of the wheel.
            ->orderBy('name')
            ->get();
    }

    /**
     * Everything else that made it onto the wheel, most picked first.
     *
     * Ordering by pick count is right here and wrong on the picking screen. The
     * danger there is a feedback loop - popular things sit at the top, get
     * picked because they are at the top, and climb further. Once the wheel has
     * been spun there is nothing left to influence.
     */
    protected function runnersUp(WellnessSession $session): Collection
    {
        return collect(Wheel::forSession($session)->slices)
            ->reject(fn (WheelSlice $slice) => $slice->activity->id === $session->activity_id)
            // Slices arrive alphabetically and PHP's sort is stable, so equal
            // pick counts stay in name order.
            ->sortByDesc(fn (WheelSlice $slice) => $slice->weight)
            ->values();
    }

    public function render(): View
    {
        $session = $this->session();
        $pickersByActivity = $this->pickersByActivity($session);

        return view('livewire.activity-picker', [
            'session' => $session,
            'activities' => $this->activities(),
            'categories' => Category::orderBy('name')->get(),
            'pickersByActivity' => $pickersByActivity,
            // Result screen. Empty collections rather than nulls so the view
            // can call isNotEmpty() without checking the state first.
            'winnerPickers' => $session?->activity_id
                ? ($pickersByActivity[$session->activity_id] ?? collect())
                : collect(),
            'runnersUp' => $session?->isDecided() ? $this->runnersUp($session) : collect(),
            // Built from the same Wheel the spin endpoint uses, so what people
            // see on screen is the wheel the server will actually spin.
            'wheel' => $session ? Wheel::forSession($session) : null,
            'everyoneHasPicked' => $session?->everyoneHasPicked() ?? false,
            'usersYetToPick' => $session?->usersYetToPick() ?? collect(),
            // The wheel a rained-off re-spin would use: the same picks minus
            // anything that needs the sky to behave.
            'rainWheel' => $session?->isDecided() && ! $session->wasRainedOff()
                ? Wheel::forSession($session, weatherProofOnly: true)
                : null,
        ]);
    }
}
