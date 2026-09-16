<?php

namespace Tests\Feature;

use App\Enums\DecisionMethod;
use App\Enums\Location;
use App\Livewire\ManageActivities;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageActivitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function activity(string $name, array $attributes = []): Activity
    {
        return Activity::factory()
            ->for($this->user, 'creator')
            ->create([...$attributes, 'name' => $name]);
    }

    private function screen()
    {
        return Livewire::actingAs($this->user)->test(ManageActivities::class);
    }

    public function test_the_screen_requires_signing_in(): void
    {
        $this->get(route('activities'))->assertRedirect('/login');
    }

    public function test_the_table_lists_activities_with_their_details(): void
    {
        $category = Category::factory()->create(['name' => 'games']);

        $this->activity('Bowling', [
            'category_id' => $category->id,
            'location' => Location::Indoor,
            'duration_minutes' => 90,
            'min_people' => 4,
        ]);

        $this->screen()
            ->assertSee('Bowling')
            ->assertSee('games')
            ->assertSee('Indoor')
            ->assertSee('90')
            ->assertSee('4');
    }

    public function test_it_shows_how_often_an_activity_has_been_picked_and_won(): void
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activity('Bowling');

        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $this->user->id,
            'activity_id' => $activity->id,
        ]);
        $session->decide($activity, DecisionMethod::Spin);

        $component = $this->screen();

        $this->assertSame(1, $component->viewData('activities')->first()->picks_count);
        $this->assertSame(1, $component->viewData('activities')->first()->decided_sessions_count);
    }

    // --- adding ------------------------------------------------------------

    public function test_an_activity_can_be_added(): void
    {
        $category = Category::factory()->create();

        $this->screen()
            ->call('startAdding')
            ->set('name', 'Crazy golf')
            ->set('categoryId', $category->id)
            ->set('location', Location::Outdoor->value)
            ->set('durationMinutes', '60')
            ->set('minPeople', '2')
            ->set('description', 'Nine holes and a windmill')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Crazy golf');

        $activity = Activity::where('name', 'Crazy golf')->sole();

        $this->assertSame(Location::Outdoor, $activity->location);
        $this->assertSame(60, $activity->duration_minutes);
        $this->assertSame(2, $activity->min_people);
        $this->assertSame($this->user->id, $activity->created_by);
        $this->assertTrue($activity->is_active);
    }

    public function test_duration_and_people_are_optional(): void
    {
        $category = Category::factory()->create();

        $this->screen()
            ->set('name', 'Open ended chat')
            ->set('categoryId', $category->id)
            ->set('location', Location::Either->value)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::where('name', 'Open ended chat')->sole();

        // Blank means unspecified, not zero - the browse filters rely on null.
        $this->assertNull($activity->duration_minutes);
        $this->assertNull($activity->min_people);
    }

    public function test_adding_validates_the_required_fields(): void
    {
        $this->screen()
            ->call('save')
            ->assertHasErrors(['name', 'categoryId', 'location']);

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_two_activities_cannot_share_a_name(): void
    {
        $category = Category::factory()->create();
        $this->activity('Bowling');

        $this->screen()
            ->set('name', 'Bowling')
            ->set('categoryId', $category->id)
            ->set('location', Location::Indoor->value)
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, Activity::where('name', 'Bowling')->count());
    }

    /**
     * The validation rule is the friendly half of this; the index is the half
     * that holds when validation is bypassed - a seeder, a console command, or
     * two people submitting the same new name at the same moment.
     */
    public function test_the_database_itself_refuses_a_duplicate_name(): void
    {
        $this->activity('Bowling');

        $this->expectException(UniqueConstraintViolationException::class);

        Activity::factory()->for($this->user, 'creator')->create(['name' => 'Bowling']);
    }

    public function test_a_silly_duration_is_refused(): void
    {
        $category = Category::factory()->create();

        $this->screen()
            ->set('name', 'Marathon')
            ->set('categoryId', $category->id)
            ->set('location', Location::Outdoor->value)
            ->set('durationMinutes', '5000')
            ->call('save')
            ->assertHasErrors(['durationMinutes']);
    }

    // --- editing -----------------------------------------------------------

    public function test_editing_loads_the_activity_into_the_form(): void
    {
        $activity = $this->activity('Bowling', [
            'location' => Location::Indoor,
            'duration_minutes' => 90,
            'min_people' => 4,
        ]);

        $this->screen()
            ->call('edit', $activity->id)
            ->assertSet('showForm', true)
            ->assertSet('editingId', $activity->id)
            ->assertSet('name', 'Bowling')
            ->assertSet('location', Location::Indoor->value)
            ->assertSet('durationMinutes', '90')
            ->assertSet('minPeople', '4');
    }

    public function test_an_activity_can_be_edited(): void
    {
        $activity = $this->activity('Bowling', ['duration_minutes' => 90]);

        $this->screen()
            ->call('edit', $activity->id)
            ->set('name', 'Ten pin bowling')
            ->set('durationMinutes', '120')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editingId', null);

        $activity->refresh();

        $this->assertSame('Ten pin bowling', $activity->name);
        $this->assertSame(120, $activity->duration_minutes);
    }

    public function test_editing_does_not_trip_the_unique_rule_on_its_own_name(): void
    {
        $activity = $this->activity('Bowling');

        $this->screen()
            ->call('edit', $activity->id)
            ->set('minPeople', '6')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(6, $activity->fresh()->min_people);
    }

    public function test_editing_still_refuses_another_activitys_name(): void
    {
        $this->activity('Bowling');
        $other = $this->activity('Coastal walk');

        $this->screen()
            ->call('edit', $other->id)
            ->set('name', 'Bowling')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame('Coastal walk', $other->fresh()->name);
    }

    public function test_cancelling_clears_the_form(): void
    {
        $activity = $this->activity('Bowling');

        $this->screen()
            ->call('edit', $activity->id)
            ->call('cancelForm')
            ->assertSet('showForm', false)
            ->assertSet('editingId', null)
            ->assertSet('name', '');
    }

    // --- retiring and restoring --------------------------------------------

    public function test_retiring_hides_an_activity_without_erasing_it(): void
    {
        $activity = $this->activity('Bowling');

        $component = $this->screen()
            ->call('retire', $activity->id)
            ->assertSee('Retired');

        // Checked against the table's own data, not the page text: the success
        // notice names the activity, so it appears on screen either way.
        $this->assertEmpty($component->viewData('activities'));

        $this->assertFalse($activity->fresh()->is_active);
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_retired_activities_can_be_shown_and_restored(): void
    {
        $activity = $this->activity('Bowling', ['is_active' => false]);

        $this->screen()
            ->set('showRetired', true)
            ->assertSee('Bowling')
            ->assertSee('Retired')
            ->call('restore', $activity->id);

        $this->assertTrue($activity->fresh()->is_active);
    }

    // --- deleting ----------------------------------------------------------

    public function test_an_unused_activity_can_be_deleted_outright(): void
    {
        $activity = $this->activity('Nobody wanted this');

        $component = $this->screen()
            ->call('confirmErase', $activity->id)
            ->assertSet('erasingId', $activity->id)
            ->call('erase')
            ->assertSet('erasingId', null);

        $this->assertEmpty($component->viewData('activities'));
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    /**
     * Deleting cascades picks away and nulls a session's activity_id, so an
     * activity with history can only be retired - otherwise a past Friday would
     * forget what the team actually did.
     */
    public function test_an_activity_with_picks_is_not_offered_for_deletion(): void
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activity('Bowling');

        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $this->user->id,
            'activity_id' => $activity->id,
        ]);

        $this->assertFalse($activity->fresh()->canBeErased());
    }

    public function test_an_activity_that_won_a_week_is_not_offered_for_deletion(): void
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activity('Bowling');
        $session->decide($activity, DecisionMethod::Spin);

        $this->assertFalse($activity->fresh()->canBeErased());
    }

    /**
     * The gap between rendering the page and pressing the button: somebody
     * could pick the activity in between, and the delete would then take their
     * pick with it.
     */
    public function test_deleting_falls_back_to_retiring_if_it_gained_history_meanwhile(): void
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activity('Bowling');

        $component = $this->screen()->call('confirmErase', $activity->id);

        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $this->user->id,
            'activity_id' => $activity->id,
        ]);

        $component->call('erase')->assertSee('retired instead of deleted');

        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'is_active' => false]);
        $this->assertDatabaseCount('picks', 1);
    }

    // --- searching and filtering -------------------------------------------

    public function test_the_search_box_narrows_the_table(): void
    {
        $this->activity('Bowling');
        $this->activity('Coastal walk');

        $this->screen()
            ->set('search', 'bowl')
            ->assertSee('Bowling')
            ->assertDontSee('Coastal walk');
    }

    public function test_searching_ignores_case(): void
    {
        $this->activity('Bowling');

        $this->screen()
            ->set('search', 'BOWLING')
            ->assertSee('Bowling');
    }

    public function test_a_fruitless_search_says_so(): void
    {
        $this->activity('Bowling');

        $this->screen()
            ->set('search', 'kayaking')
            ->assertSee('Nothing matches that search');
    }

    public function test_retired_activities_stay_out_of_the_table_by_default(): void
    {
        $this->activity('Retired thing', ['is_active' => false]);

        $this->screen()->assertDontSee('Retired thing');
    }
}
