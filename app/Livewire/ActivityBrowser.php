<?php

namespace App\Livewire;

use App\Enums\Location;
use App\Livewire\Concerns\PicksActivities;
use App\Models\Activity;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Random\Randomizer;

/**
 * "We have forty minutes and four people - what can we actually do?"
 *
 * Separate from the wheel on purpose. The wheel is how the team commits to
 * Friday; this is for the times nobody is sure what is even possible. It
 * decides nothing, but it can still add to this week's picks when a session
 * happens to be open, so browsing during the Monday meeting is not a dead end.
 */
#[Layout('layouts.app')]
class ActivityBrowser extends Component
{
    use PicksActivities;

    /** Filters live in the URL so a set of them can be sent to someone. */
    #[Url(as: 'in', except: [])]
    public array $categoryIds = [];

    #[Url(as: 'where', except: '')]
    public string $location = '';

    #[Url(as: 'mins', except: '')]
    public string $maxDuration = '';

    #[Url(as: 'people', except: '')]
    public string $groupSize = '';

    /** The activity the shuffle landed on, if it has been used. */
    public ?int $suggestionId = null;

    public const DURATIONS = [30, 60, 90, 120];

    public const GROUP_SIZES = [2, 3, 4, 6, 8];

    public function mount(): void
    {
        $this->picks = $this->loadPicks();
    }

    public function toggleCategory(int $categoryId): void
    {
        $this->categoryIds = in_array($categoryId, $this->categoryIds, true)
            ? array_values(array_diff($this->categoryIds, [$categoryId]))
            : [...$this->categoryIds, $categoryId];

        $this->filtersChanged();
    }

    public function setLocation(string $location): void
    {
        $this->location = $this->location === $location ? '' : $location;

        $this->filtersChanged();
    }

    public function setMaxDuration(string $minutes): void
    {
        $this->maxDuration = $this->maxDuration === $minutes ? '' : $minutes;

        $this->filtersChanged();
    }

    public function setGroupSize(string $size): void
    {
        $this->groupSize = $this->groupSize === $size ? '' : $size;

        $this->filtersChanged();
    }

    public function clearFilters(): void
    {
        $this->reset('categoryIds', 'location', 'maxDuration', 'groupSize');

        $this->filtersChanged();
    }

    public function hasFilters(): bool
    {
        return $this->categoryIds !== []
            || $this->location !== ''
            || $this->maxDuration !== ''
            || $this->groupSize !== '';
    }

    /**
     * A suggestion only means anything for the results that produced it, and a
     * stale "you have used all your picks" message helps nobody either.
     */
    protected function filtersChanged(): void
    {
        $this->suggestionId = null;
        $this->blockedBy = null;
    }

    /** Nudge for when nobody can choose. Decides nothing and commits to nothing. */
    public function surpriseMe(): void
    {
        $matches = $this->results();

        if ($matches->isEmpty()) {
            $this->suggestionId = null;

            return;
        }

        // Resolved from the container rather than built here so a test can seed
        // it, the same way the spin endpoint does.
        $randomizer = app(Randomizer::class);

        // Unweighted on purpose: unlike the wheel, popularity has no say here.
        $this->suggestionId = $matches[$randomizer->getInt(0, $matches->count() - 1)]->id;
    }

    protected function results(): Collection
    {
        return Activity::query()
            ->active()
            ->with('category')
            ->when($this->categoryIds, fn ($query, $ids) => $query->whereIn('category_id', $ids))
            ->when($this->locationFilter(), fn ($query, Location $location) => $query->fitsLocation($location))
            ->when($this->maxDuration !== '', fn ($query) => $query->fitsWithin((int) $this->maxDuration))
            ->when($this->groupSize !== '', fn ($query) => $query->worksWith((int) $this->groupSize))
            ->orderBy('name')
            ->get();
    }

    protected function locationFilter(): ?Location
    {
        return Location::tryFrom($this->location);
    }

    public function render(): View
    {
        $session = $this->session();
        $results = $this->results();

        return view('livewire.activity-browser', [
            'session' => $session,
            'results' => $results,
            'categories' => Category::orderBy('name')->get(),
            'pickersByActivity' => $this->pickersByActivity($session),
            'suggestion' => $this->suggestionId
                ? $results->firstWhere('id', $this->suggestionId)
                : null,
            'totalActive' => Activity::query()->active()->count(),
        ]);
    }
}
