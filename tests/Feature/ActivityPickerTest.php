<?php

namespace Tests\Feature;

use App\Enums\Location;
use App\Livewire\ActivityPicker;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityPickerTest extends TestCase
{
    use RefreshDatabase;

    private function openSession(): WellnessSession
    {
        return WellnessSession::factory()->create([
            'meeting_date' => WellnessSession::comingFriday(),
        ]);
    }

    public function test_it_records_a_pick_and_reflects_it_in_the_picks_array(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession();
        $activity = Activity::factory()->create();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->call('toggle', $activity->id)
            ->assertSet('picks', [$activity->id]);

        $this->assertDatabaseHas('picks', [
            'wellness_session_id' => $session->id,
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_tapping_a_picked_row_un_picks_it(): void
    {
        $user = User::factory()->create();
        $this->openSession();
        $activity = Activity::factory()->create();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->call('toggle', $activity->id)
            ->call('toggle', $activity->id)
            ->assertSet('picks', []);

        $this->assertDatabaseCount('picks', 0);
    }

    public function test_a_fourth_pick_is_refused_and_explained_rather_than_disabled(): void
    {
        $user = User::factory()->create();
        $this->openSession();
        $activities = Activity::factory()->count(4)->create();

        $component = Livewire::actingAs($user)->test(ActivityPicker::class);

        foreach ($activities->take(3) as $activity) {
            $component->call('toggle', $activity->id);
        }

        $fourth = $activities->last();
        $component->call('toggle', $fourth->id);

        // Refused, but the component records which row was tapped so the view
        // can explain itself inline instead of showing a dead control.
        $component->assertCount('picks', 3)
            ->assertSet('blockedBy', $fourth->id);

        $this->assertDatabaseMissing('picks', ['activity_id' => $fourth->id]);
    }

    public function test_un_picking_frees_the_slot_again(): void
    {
        $user = User::factory()->create();
        $this->openSession();
        $activities = Activity::factory()->count(4)->create();

        $component = Livewire::actingAs($user)->test(ActivityPicker::class);

        foreach ($activities->take(3) as $activity) {
            $component->call('toggle', $activity->id);
        }

        $component->call('toggle', $activities->first()->id)
            ->call('toggle', $activities->last()->id)
            ->assertCount('picks', 3)
            ->assertSet('blockedBy', null);

        $this->assertDatabaseHas('picks', ['activity_id' => $activities->last()->id]);
    }

    public function test_picks_are_loaded_on_mount(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession();
        $activity = Activity::factory()->create();

        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSet('picks', [$activity->id]);
    }

    public function test_one_persons_picks_do_not_leak_into_anothers(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $session = $this->openSession();
        $activity = Activity::factory()->create();

        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $alice->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::actingAs($bob)
            ->test(ActivityPicker::class)
            ->assertSet('picks', []);
    }

    public function test_activities_are_listed_alphabetically_not_by_popularity(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession();

        $zebra = Activity::factory()->create(['name' => 'Zebra walk']);
        $apple = Activity::factory()->create(['name' => 'Apple picking']);

        // Make the last-alphabetically activity the most popular one.
        foreach (User::factory()->count(3)->create() as $other) {
            Pick::create([
                'wellness_session_id' => $session->id,
                'user_id' => $other->id,
                'activity_id' => $zebra->id,
            ]);
        }

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSeeInOrder([$apple->name, $zebra->name]);
    }

    public function test_retired_activities_are_not_listed(): void
    {
        $user = User::factory()->create();
        $this->openSession();

        $live = Activity::factory()->create(['name' => 'Still offered']);
        $retired = Activity::factory()->retired()->create(['name' => 'No longer offered']);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee($live->name)
            ->assertDontSee($retired->name);
    }

    public function test_the_category_filter_narrows_the_list(): void
    {
        $user = User::factory()->create();
        $this->openSession();

        $games = Category::factory()->create(['name' => 'games']);
        $inCategory = Activity::factory()->create(['name' => 'Bowling', 'category_id' => $games->id]);
        $outOfCategory = Activity::factory()->create(['name' => 'Coastal walk']);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->call('filterBy', $games->id)
            ->assertSee($inCategory->name)
            ->assertDontSee($outOfCategory->name);
    }

    public function test_adding_an_activity_spends_a_pick(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession();
        $category = Category::factory()->create();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->set('newName', 'Crazy golf')
            ->set('newCategoryId', $category->id)
            ->set('newLocation', Location::Outdoor->value)
            ->call('addActivity')
            ->assertHasNoErrors()
            ->assertCount('picks', 1);

        $activity = Activity::where('name', 'Crazy golf')->sole();

        $this->assertSame($user->id, $activity->created_by);
        $this->assertDatabaseHas('picks', [
            'wellness_session_id' => $session->id,
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_an_activity_cannot_be_added_without_a_pick_to_spend(): void
    {
        $user = User::factory()->create();
        $this->openSession();
        $category = Category::factory()->create();

        $component = Livewire::actingAs($user)->test(ActivityPicker::class);

        foreach (Activity::factory()->count(3)->create() as $activity) {
            $component->call('toggle', $activity->id);
        }

        $component->set('newName', 'Crazy golf')
            ->set('newCategoryId', $category->id)
            ->set('newLocation', Location::Outdoor->value)
            ->call('addActivity')
            ->assertHasErrors('newName');

        $this->assertDatabaseMissing('activities', ['name' => 'Crazy golf']);
    }

    public function test_the_quick_add_form_refuses_a_name_that_already_exists(): void
    {
        $user = User::factory()->create();
        $this->openSession();
        $category = Category::factory()->create();
        Activity::factory()->create(['name' => 'Bowling']);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->set('newName', 'Bowling')
            ->set('newCategoryId', $category->id)
            ->set('newLocation', Location::Indoor->value)
            ->call('addActivity')
            ->assertHasErrors(['newName' => 'unique']);

        $this->assertSame(1, Activity::where('name', 'Bowling')->count());
    }

    public function test_adding_an_activity_validates_its_fields(): void
    {
        $user = User::factory()->create();
        $this->openSession();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->set('newName', '')
            ->call('addActivity')
            ->assertHasErrors(['newName', 'newCategoryId', 'newLocation']);
    }

    public function test_picking_is_refused_once_the_session_is_decided(): void
    {
        $user = User::factory()->create();
        WellnessSession::factory()->decided()->create([
            'meeting_date' => WellnessSession::comingFriday(),
        ]);
        $activity = Activity::factory()->create();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->call('toggle', $activity->id)
            ->assertSet('picks', []);

        $this->assertDatabaseCount('picks', 0);
    }

    public function test_start_this_week_opens_a_session_for_the_coming_friday(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee('Start this week')
            ->call('startWeek');

        $session = WellnessSession::sole();

        $this->assertTrue($session->isOpen());
        $this->assertTrue($session->meeting_date->isFriday());
    }

    public function test_starting_a_week_twice_does_not_open_a_second_session(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->call('startWeek')
            ->call('startWeek');

        $this->assertDatabaseCount('wellness_sessions', 1);
    }

    public function test_the_new_badge_shows_only_for_recently_added_activities(): void
    {
        $user = User::factory()->create();
        $this->openSession();

        Activity::factory()->create(['name' => 'Just added']);
        Activity::factory()
            ->create(['name' => 'Long standing'])
            ->forceFill(['created_at' => now()->subDays(Activity::NEW_FOR_DAYS + 1)])
            ->save();

        $component = Livewire::actingAs($user)->test(ActivityPicker::class);

        $this->assertSame(1, substr_count($component->html(), '>New</span>'));
    }
}
