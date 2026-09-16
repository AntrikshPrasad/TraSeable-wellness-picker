<?php

namespace Tests\Feature;

use App\Enums\DecisionMethod;
use App\Enums\Location;
use App\Livewire\ActivityPicker;
use App\Models\Activity;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResultScreenTest extends TestCase
{
    use RefreshDatabase;

    private function pick(WellnessSession $session, Activity $activity, User $user): void
    {
        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);
    }

    private function activityBy(User $creator, array $attributes = []): Activity
    {
        return Activity::factory()->for($creator, 'creator')->create($attributes);
    }

    public function test_it_shows_the_winner_with_its_date_location_and_duration(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $winner = $this->activityBy($user, [
            'name' => 'Coastal walk',
            'location' => Location::Outdoor,
            'duration_minutes' => 90,
            'min_people' => 4,
        ]);

        $this->pick($session, $winner, $user);
        $session->decide($winner, DecisionMethod::Spin);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee('Coastal walk')
            ->assertSee($session->meeting_date->format('l j F'))
            ->assertSee('Outdoor')
            ->assertSee('90 min')
            ->assertSee('4+ people');
    }

    public function test_it_names_everyone_who_picked_the_winner(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Chen']);
        $bob = User::factory()->create(['name' => 'Bob Ruka']);
        $carol = User::factory()->create(['name' => 'Carol Vunivalu']);

        $session = WellnessSession::factory()->create();
        $winner = $this->activityBy($alice, ['name' => 'Bowling']);
        $other = $this->activityBy($alice, ['name' => 'Trivia quiz']);

        $this->pick($session, $winner, $alice);
        $this->pick($session, $winner, $bob);
        $this->pick($session, $other, $carol);

        $session->decide($winner, DecisionMethod::Spin);

        Livewire::actingAs($alice)
            ->test(ActivityPicker::class)
            ->assertSee('Picked by Alice Chen and Bob Ruka')
            // Carol picked something else, so she is not credited with this one.
            ->assertDontSee('Picked by Alice Chen, Bob Ruka and Carol Vunivalu');
    }

    public function test_runners_up_are_listed_with_their_pick_counts_most_picked_first(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $winner = $this->activityBy($user, ['name' => 'Bowling']);
        $popular = $this->activityBy($user, ['name' => 'Team lunch']);
        $quiet = $this->activityBy($user, ['name' => 'Pottery class']);

        $this->pick($session, $winner, $user);

        foreach (User::factory()->count(3)->create() as $other) {
            $this->pick($session, $popular, $other);
        }

        $this->pick($session, $quiet, User::factory()->create());

        $session->decide($winner, DecisionMethod::Spin);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee('Also on the wheel')
            ->assertSeeInOrder(['Team lunch', '3 picks', 'Pottery class', '1 pick'])
            // The winner headlines the screen; it is not also a runner-up.
            ->assertSeeInOrder(['Bowling', 'Also on the wheel']);
    }

    public function test_the_winner_is_not_listed_among_the_runners_up(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $winner = $this->activityBy($user, ['name' => 'Bowling']);
        $this->pick($session, $winner, $user);
        $session->decide($winner, DecisionMethod::Spin);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            // Nothing else was picked, so there is no runner-up section at all.
            ->assertDontSee('Also on the wheel');
    }

    public function test_a_rained_off_result_says_so(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $indoor = Activity::factory()->indoor()->for($user, 'creator')->create(['name' => 'Bowling']);
        $this->pick($session, $indoor, $user);

        $session->decide($indoor, DecisionMethod::Spin, rainedOff: true);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee('rained off');
    }

    public function test_an_ordinary_result_carries_no_rained_off_note(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $activity = $this->activityBy($user);
        $this->pick($session, $activity, $user);
        $session->decide($activity, DecisionMethod::Spin);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertDontSee('rained off');
    }

    public function test_the_re_spin_offer_appears_only_while_something_indoors_was_picked(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $outdoorWinner = Activity::factory()->outdoor()->for($user, 'creator')->create(['name' => 'Coastal walk']);
        $this->pick($session, $outdoorWinner, $user);
        $session->decide($outdoorWinner, DecisionMethod::Spin);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertDontSee('Re-spin indoors');

        // Now somebody had also picked something that works in the rain.
        $indoor = Activity::factory()->indoor()->for($user, 'creator')->create(['name' => 'Bowling']);
        $this->pick($session, $indoor, User::factory()->create());

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee('Re-spin indoors');
    }

    public function test_the_re_spin_offer_disappears_once_it_has_been_used(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $indoor = Activity::factory()->indoor()->for($user, 'creator')->create();
        $this->pick($session, $indoor, $user);
        $session->decide($indoor, DecisionMethod::Spin, rainedOff: true);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertDontSee('Re-spin indoors');
    }

    public function test_a_skipped_week_says_so_instead(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $session->skip();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee('This week was skipped')
            ->assertDontSee('Also on the wheel');
    }
}
