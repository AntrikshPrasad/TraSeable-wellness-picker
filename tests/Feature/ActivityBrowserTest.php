<?php

namespace Tests\Feature;

use App\Enums\DecisionMethod;
use App\Enums\Location;
use App\Livewire\ActivityBrowser;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class ActivityBrowserTest extends TestCase
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

    private function browser()
    {
        return Livewire::actingAs($this->user)->test(ActivityBrowser::class);
    }

    public function test_browsing_requires_signing_in(): void
    {
        $this->get(route('browse'))->assertRedirect('/login');
    }

    public function test_it_lists_every_active_activity_by_default(): void
    {
        $this->activity('Bowling');
        $this->activity('Coastal walk');
        $this->activity('Retired thing', ['is_active' => false]);

        $this->browser()
            ->assertSee('Bowling')
            ->assertSee('Coastal walk')
            ->assertDontSee('Retired thing');
    }

    public function test_the_category_filter_takes_more_than_one_at_a_time(): void
    {
        $games = Category::factory()->create(['name' => 'games']);
        $food = Category::factory()->create(['name' => 'food']);

        $this->activity('Bowling', ['category_id' => $games->id]);
        $this->activity('Bake-off', ['category_id' => $food->id]);
        $this->activity('Coastal walk');

        $this->browser()
            ->call('toggleCategory', $games->id)
            ->assertSee('Bowling')
            ->assertDontSee('Bake-off')
            ->call('toggleCategory', $food->id)
            ->assertSee('Bowling')
            ->assertSee('Bake-off')
            ->assertDontSee('Coastal walk');
    }

    public function test_tapping_a_selected_category_again_removes_it(): void
    {
        $games = Category::factory()->create(['name' => 'games']);
        $this->activity('Bowling', ['category_id' => $games->id]);
        $this->activity('Coastal walk');

        $this->browser()
            ->call('toggleCategory', $games->id)
            ->assertDontSee('Coastal walk')
            ->call('toggleCategory', $games->id)
            ->assertSet('categoryIds', [])
            ->assertSee('Coastal walk');
    }

    /**
     * The subtlety worth guarding: an "either" activity works indoors as much
     * as anything does, so filtering for indoor must not hide it.
     */
    public function test_filtering_by_indoor_includes_either_activities(): void
    {
        $this->activity('Bowling', ['location' => Location::Indoor]);
        $this->activity('Team lunch', ['location' => Location::Either]);
        $this->activity('Coastal walk', ['location' => Location::Outdoor]);

        $this->browser()
            ->call('setLocation', Location::Indoor->value)
            ->assertSee('Bowling')
            ->assertSee('Team lunch')
            ->assertDontSee('Coastal walk');
    }

    public function test_filtering_by_outdoor_includes_either_activities(): void
    {
        $this->activity('Bowling', ['location' => Location::Indoor]);
        $this->activity('Team lunch', ['location' => Location::Either]);
        $this->activity('Coastal walk', ['location' => Location::Outdoor]);

        $this->browser()
            ->call('setLocation', Location::Outdoor->value)
            ->assertSee('Coastal walk')
            ->assertSee('Team lunch')
            ->assertDontSee('Bowling');
    }

    public function test_the_time_filter_keeps_anything_that_fits(): void
    {
        $this->activity('Quick quiz', ['duration_minutes' => 30]);
        $this->activity('Bowling', ['duration_minutes' => 90]);
        $this->activity('Long hike', ['duration_minutes' => 180]);

        $this->browser()
            ->call('setMaxDuration', '90')
            ->assertSee('Quick quiz')
            ->assertSee('Bowling')
            ->assertDontSee('Long hike');
    }

    /**
     * A blank duration means nobody recorded one, not that it takes all day.
     * Hiding those would punish activities for an unfilled field.
     */
    public function test_activities_with_no_stated_duration_survive_the_time_filter(): void
    {
        $this->activity('Unknown length', ['duration_minutes' => null]);
        $this->activity('Long hike', ['duration_minutes' => 180]);

        $this->browser()
            ->call('setMaxDuration', '30')
            ->assertSee('Unknown length')
            ->assertDontSee('Long hike');
    }

    public function test_the_group_size_filter_hides_things_needing_more_people(): void
    {
        $this->activity('Board game', ['min_people' => 3]);
        $this->activity('Five a side', ['min_people' => 10]);
        $this->activity('Solo stroll', ['min_people' => null]);

        $this->browser()
            ->call('setGroupSize', '4')
            ->assertSee('Board game')
            ->assertSee('Solo stroll')
            ->assertDontSee('Five a side');
    }

    public function test_filters_combine(): void
    {
        $games = Category::factory()->create(['name' => 'games']);

        $this->activity('Quick indoor game', [
            'category_id' => $games->id,
            'location' => Location::Indoor,
            'duration_minutes' => 30,
            'min_people' => 2,
        ]);
        $this->activity('Long indoor game', [
            'category_id' => $games->id,
            'location' => Location::Indoor,
            'duration_minutes' => 180,
            'min_people' => 2,
        ]);
        $this->activity('Quick outdoor game', [
            'category_id' => $games->id,
            'location' => Location::Outdoor,
            'duration_minutes' => 30,
            'min_people' => 2,
        ]);

        $this->browser()
            ->call('toggleCategory', $games->id)
            ->call('setLocation', Location::Indoor->value)
            ->call('setMaxDuration', '60')
            ->call('setGroupSize', '2')
            ->assertSee('Quick indoor game')
            ->assertDontSee('Long indoor game')
            ->assertDontSee('Quick outdoor game');
    }

    public function test_an_impossible_combination_explains_itself(): void
    {
        $this->activity('Long hike', ['duration_minutes' => 180, 'min_people' => 10]);

        $this->browser()
            ->call('setMaxDuration', '30')
            ->assertSee('Nothing matches all of that')
            ->assertSee('Clear all filters');
    }

    public function test_clearing_filters_brings_everything_back(): void
    {
        $this->activity('Long hike', ['duration_minutes' => 180]);

        $this->browser()
            ->call('setMaxDuration', '30')
            ->assertDontSee('Long hike')
            ->call('clearFilters')
            ->assertSet('maxDuration', '')
            ->assertSee('Long hike');
    }

    public function test_surprise_me_suggests_something_from_the_filtered_results(): void
    {
        $this->swap(Randomizer::class, new Randomizer(new Mt19937(20260915)));

        $games = Category::factory()->create(['name' => 'games']);
        $this->activity('Bowling', ['category_id' => $games->id]);
        $this->activity('Board game', ['category_id' => $games->id]);
        $this->activity('Coastal walk');

        $component = $this->browser()
            ->call('toggleCategory', $games->id)
            ->call('surpriseMe')
            ->assertSee('How about');

        $suggested = Activity::find($component->get('suggestionId'));

        // Never something the filters just ruled out.
        $this->assertContains($suggested->name, ['Bowling', 'Board game']);
    }

    public function test_changing_a_filter_drops_a_stale_suggestion(): void
    {
        $this->swap(Randomizer::class, new Randomizer(new Mt19937(1)));

        $this->activity('Bowling');
        $this->activity('Coastal walk');

        $this->browser()
            ->call('surpriseMe')
            ->assertNotSet('suggestionId', null)
            ->call('setMaxDuration', '30')
            ->assertSet('suggestionId', null)
            ->assertDontSee('How about');
    }

    public function test_surprise_me_does_nothing_when_nothing_fits(): void
    {
        $this->activity('Long hike', ['duration_minutes' => 180]);

        $this->browser()
            ->call('setMaxDuration', '30')
            ->call('surpriseMe')
            ->assertSet('suggestionId', null);
    }

    public function test_activities_can_be_picked_from_here_while_a_session_is_open(): void
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activity('Bowling');

        $this->browser()
            ->assertSee("this week's wheel", escape: false)
            ->call('toggle', $activity->id)
            ->assertSet('picks', [$activity->id]);

        $this->assertDatabaseHas('picks', [
            'wellness_session_id' => $session->id,
            'user_id' => $this->user->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_the_three_pick_limit_applies_here_too(): void
    {
        WellnessSession::factory()->create();
        $activities = collect(['A one', 'A two', 'A three', 'A four'])
            ->map(fn (string $name) => $this->activity($name));

        $component = $this->browser();

        foreach ($activities->take(3) as $activity) {
            $component->call('toggle', $activity->id);
        }

        $fourth = $activities->last();

        $component->call('toggle', $fourth->id)
            ->assertCount('picks', 3)
            ->assertSet('blockedBy', $fourth->id);

        $this->assertDatabaseMissing('picks', ['activity_id' => $fourth->id]);
    }

    public function test_picks_made_on_the_monday_screen_show_as_picked_here(): void
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activity('Bowling');

        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $this->user->id,
            'activity_id' => $activity->id,
        ]);

        $this->browser()->assertSet('picks', [$activity->id]);
    }

    public function test_there_is_nothing_to_tap_when_no_session_is_open(): void
    {
        $activity = $this->activity('Bowling');

        $this->browser()
            ->assertSee('Bowling')
            ->assertDontSee("this week's wheel", escape: false)
            ->call('toggle', $activity->id)
            ->assertSet('picks', []);

        $this->assertDatabaseCount('picks', 0);
    }

    public function test_picking_is_closed_here_once_the_week_is_decided(): void
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activity('Bowling');
        $session->decide($activity, DecisionMethod::Spin);

        $this->browser()
            ->assertDontSee("this week's wheel", escape: false)
            ->call('toggle', $activity->id)
            ->assertSet('picks', []);
    }

    public function test_filters_are_shareable_through_the_url(): void
    {
        $games = Category::factory()->create(['name' => 'games']);
        $this->activity('Bowling', ['category_id' => $games->id]);
        $this->activity('Coastal walk');

        // What someone would land on from a pasted link.
        Livewire::actingAs($this->user)
            ->withQueryParams(['in' => [$games->id], 'mins' => '90'])
            ->test(ActivityBrowser::class)
            ->assertSet('categoryIds', [$games->id])
            ->assertSet('maxDuration', '90')
            ->assertSee('Bowling')
            ->assertDontSee('Coastal walk');
    }
}
