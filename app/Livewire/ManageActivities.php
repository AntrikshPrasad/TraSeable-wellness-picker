<?php

namespace App\Livewire;

use App\Enums\Location;
use App\Models\Activity;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The activity pool itself: everything the wheel and the browser draw from.
 *
 * Retiring is the normal way to remove something. Erasing is offered only for
 * activities nothing points at yet - see Activity::canBeErased().
 */
#[Layout('layouts.app')]
class ManageActivities extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'retired', except: false)]
    public bool $showRetired = false;

    /** Null while adding, the activity's id while editing. */
    public ?int $editingId = null;

    public bool $showForm = false;

    /** The activity the erase dialog is asking about. */
    public ?int $erasingId = null;

    public ?string $notice = null;

    public string $name = '';

    public ?int $categoryId = null;

    public string $location = '';

    public ?string $durationMinutes = null;

    public ?string $minPeople = null;

    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                // Two activities with the same name would be indistinguishable
                // on the wheel, where all anyone sees is the name.
                Rule::unique('activities', 'name')->ignore($this->editingId),
            ],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'location' => ['required', Rule::enum(Location::class)],
            'durationMinutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'minPeople' => ['nullable', 'integer', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'categoryId' => 'category',
            'durationMinutes' => 'duration',
            'minPeople' => 'minimum people',
        ];
    }

    public function startAdding(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $activityId): void
    {
        $activity = Activity::findOrFail($activityId);

        $this->editingId = $activity->id;
        $this->name = $activity->name;
        $this->categoryId = $activity->category_id;
        $this->location = $activity->location->value;
        $this->durationMinutes = (string) $activity->duration_minutes;
        $this->minPeople = (string) $activity->min_people;
        $this->description = (string) $activity->description;

        $this->resetValidation();
        $this->showForm = true;
        $this->notice = null;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'category_id' => $validated['categoryId'],
            'location' => $validated['location'],
            'duration_minutes' => $validated['durationMinutes'] ?: null,
            'min_people' => $validated['minPeople'] ?: null,
            'description' => $validated['description'] ?: null,
        ];

        if ($this->editingId) {
            Activity::findOrFail($this->editingId)->update($attributes);
            $this->notice = "Saved \"{$validated['name']}\".";
        } else {
            Activity::create([...$attributes, 'created_by' => auth()->id()]);
            $this->notice = "Added \"{$validated['name']}\".";
        }

        $this->resetForm();
    }

    /**
     * Take an activity out of circulation without erasing it. Past picks and
     * results keep pointing at something real, and it can come back.
     */
    public function retire(int $activityId): void
    {
        $activity = Activity::findOrFail($activityId);
        $activity->update(['is_active' => false]);

        $this->notice = "Retired \"{$activity->name}\". It will not appear on the wheel again unless you restore it.";
    }

    public function restore(int $activityId): void
    {
        $activity = Activity::findOrFail($activityId);
        $activity->update(['is_active' => true]);

        $this->notice = "Restored \"{$activity->name}\".";
    }

    public function confirmErase(int $activityId): void
    {
        $this->erasingId = $activityId;
        $this->notice = null;
    }

    public function erase(): void
    {
        $activity = Activity::withCount(['picks', 'decidedSessions'])->find($this->erasingId);

        $this->erasingId = null;

        if (! $activity) {
            return;
        }

        // Re-checked here rather than trusted from the view: history could have
        // arrived between the page rendering and the button being pressed.
        if (! $activity->canBeErased()) {
            $this->notice = "\"{$activity->name}\" has been picked since this page loaded, so it was retired instead of deleted.";
            $activity->update(['is_active' => false]);

            return;
        }

        $name = $activity->name;
        $activity->delete();

        $this->notice = "Deleted \"{$name}\".";
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'showForm', 'name', 'categoryId', 'location', 'durationMinutes', 'minPeople', 'description');
        $this->resetValidation();
    }

    protected function activities(): Collection
    {
        return Activity::query()
            ->withCount(['picks', 'decidedSessions'])
            ->with('category')
            ->when(! $this->showRetired, fn ($query) => $query->active())
            ->when($this->search !== '', fn ($query) => $query->whereLike('name', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.manage-activities', [
            'activities' => $this->activities(),
            'categories' => Category::orderBy('name')->get(),
            'erasing' => $this->erasingId ? Activity::find($this->erasingId) : null,
            'retiredCount' => Activity::query()->where('is_active', false)->count(),
        ]);
    }
}
