<?php

namespace Tests\Unit;

use App\Enums\Location;
use App\Models\Activity;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use App\Support\Wheel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class WheelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Give an activity a number of picks by that many distinct users.
     * Distinct because the unique index forbids one person picking twice.
     */
    private function pick(WellnessSession $session, Activity $activity, int $times): void
    {
        foreach (User::factory()->count($times)->create() as $user) {
            Pick::create([
                'wellness_session_id' => $session->id,
                'user_id' => $user->id,
                'activity_id' => $activity->id,
            ]);
        }
    }

    public function test_only_picked_activities_get_a_slice(): void
    {
        $session = WellnessSession::factory()->create();
        $picked = Activity::factory()->create(['name' => 'Bowling']);
        Activity::factory()->create(['name' => 'Ignored']);

        $this->pick($session, $picked, 1);

        $wheel = Wheel::forSession($session);

        $this->assertCount(1, $wheel->slices);
        $this->assertSame('Bowling', $wheel->slices[0]->activity->name);
    }

    public function test_slice_weight_is_the_pick_count(): void
    {
        $session = WellnessSession::factory()->create();
        $popular = Activity::factory()->create(['name' => 'Alpha']);
        $lonely = Activity::factory()->create(['name' => 'Beta']);

        $this->pick($session, $popular, 3);
        $this->pick($session, $lonely, 1);

        $wheel = Wheel::forSession($session);

        $this->assertSame(3, $wheel->slices[0]->weight);
        $this->assertSame(1, $wheel->slices[1]->weight);
        $this->assertSame(4, $wheel->totalWeight());
    }

    public function test_slice_angles_are_proportional_and_cover_the_whole_circle(): void
    {
        $session = WellnessSession::factory()->create();
        $threeQuarters = Activity::factory()->create(['name' => 'Alpha']);
        $oneQuarter = Activity::factory()->create(['name' => 'Beta']);

        $this->pick($session, $threeQuarters, 3);
        $this->pick($session, $oneQuarter, 1);

        $wheel = Wheel::forSession($session);

        $this->assertEqualsWithDelta(270.0, $wheel->slices[0]->sweep(), 0.0001);
        $this->assertEqualsWithDelta(90.0, $wheel->slices[1]->sweep(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $wheel->slices[0]->startAngle, 0.0001);
        $this->assertEqualsWithDelta(360.0, $wheel->slices[1]->endAngle, 0.0001);
    }

    public function test_slices_are_ordered_alphabetically_not_by_weight(): void
    {
        $session = WellnessSession::factory()->create();
        $zebra = Activity::factory()->create(['name' => 'Zebra walk']);
        $apple = Activity::factory()->create(['name' => 'Apple picking']);

        // Make the alphabetically last activity by far the most popular.
        $this->pick($session, $zebra, 5);
        $this->pick($session, $apple, 1);

        $wheel = Wheel::forSession($session);

        $this->assertSame('Apple picking', $wheel->slices[0]->activity->name);
        $this->assertSame('Zebra walk', $wheel->slices[1]->activity->name);
        $this->assertSame([0, 1], array_column($wheel->slices, 'index'));
    }

    /**
     * The exact boundary test. Every ticket from 1 to the total is claimed by
     * exactly one slice, and the claims line up with the weights: tickets 1-3
     * belong to the activity with three picks, ticket 4 to the one with one.
     */
    public function test_every_ticket_maps_to_the_slice_its_weight_earns(): void
    {
        $session = WellnessSession::factory()->create();
        $three = Activity::factory()->create(['name' => 'Alpha']);
        $one = Activity::factory()->create(['name' => 'Beta']);
        $two = Activity::factory()->create(['name' => 'Gamma']);

        $this->pick($session, $three, 3);
        $this->pick($session, $one, 1);
        $this->pick($session, $two, 2);

        $wheel = Wheel::forSession($session);

        $expected = [
            1 => 'Alpha', 2 => 'Alpha', 3 => 'Alpha',
            4 => 'Beta',
            5 => 'Gamma', 6 => 'Gamma',
        ];

        foreach ($expected as $ticket => $name) {
            $this->assertSame(
                $name,
                $wheel->sliceForTicket($ticket)->activity->name,
                "Ticket {$ticket} landed on the wrong slice",
            );
        }
    }

    public function test_a_ticket_outside_the_range_is_a_programming_error(): void
    {
        $session = WellnessSession::factory()->create();
        $this->pick($session, Activity::factory()->create(), 2);

        $wheel = Wheel::forSession($session);

        $this->expectException(LogicException::class);
        $wheel->sliceForTicket(3);
    }

    public function test_spinning_an_empty_wheel_is_refused(): void
    {
        $wheel = Wheel::forSession(WellnessSession::factory()->create());

        $this->assertTrue($wheel->isEmpty());

        $this->expectException(LogicException::class);
        $wheel->spin(new Randomizer);
    }

    /**
     * The distribution test the brief asks for: over many spins, each activity
     * wins roughly in proportion to its picks. Seeded so it is repeatable -
     * a flaky test here would be worse than no test.
     */
    public function test_winners_are_distributed_in_proportion_to_pick_counts(): void
    {
        $session = WellnessSession::factory()->create();
        $six = Activity::factory()->create(['name' => 'Alpha']);
        $three = Activity::factory()->create(['name' => 'Beta']);
        $one = Activity::factory()->create(['name' => 'Gamma']);

        $this->pick($session, $six, 6);
        $this->pick($session, $three, 3);
        $this->pick($session, $one, 1);

        $wheel = Wheel::forSession($session);
        $randomizer = new Randomizer(new Mt19937(20260915));

        $spins = 20000;
        $wins = ['Alpha' => 0, 'Beta' => 0, 'Gamma' => 0];

        for ($i = 0; $i < $spins; $i++) {
            $wins[$wheel->spin($randomizer)->activity->name]++;
        }

        // Expected shares are 6/10, 3/10 and 1/10. Two points of tolerance is
        // far wider than the sampling error at 20k spins but still nowhere near
        // wide enough to hide a genuinely wrong weighting.
        $this->assertEqualsWithDelta(0.6, $wins['Alpha'] / $spins, 0.02);
        $this->assertEqualsWithDelta(0.3, $wins['Beta'] / $spins, 0.02);
        $this->assertEqualsWithDelta(0.1, $wins['Gamma'] / $spins, 0.02);
    }

    public function test_an_unpopular_activity_still_wins_sometimes(): void
    {
        $session = WellnessSession::factory()->create();
        $favourite = Activity::factory()->create(['name' => 'Alpha']);
        $underdog = Activity::factory()->create(['name' => 'Beta']);

        $this->pick($session, $favourite, 9);
        $this->pick($session, $underdog, 1);

        $wheel = Wheel::forSession($session);
        $randomizer = new Randomizer(new Mt19937(7));

        $underdogWins = 0;

        for ($i = 0; $i < 500; $i++) {
            if ($wheel->spin($randomizer)->activity->name === 'Beta') {
                $underdogWins++;
            }
        }

        // The whole self-correcting popularity argument in the brief rests on a
        // one-pick activity being possible, not merely unlikely.
        $this->assertGreaterThan(0, $underdogWins);
    }

    public function test_the_weather_proof_wheel_drops_outdoor_activities(): void
    {
        $session = WellnessSession::factory()->create();
        $indoor = Activity::factory()->indoor()->create(['name' => 'Bowling']);
        $either = Activity::factory()->create(['name' => 'Team lunch', 'location' => Location::Either]);
        $outdoor = Activity::factory()->outdoor()->create(['name' => 'Coastal walk']);

        $this->pick($session, $indoor, 1);
        $this->pick($session, $either, 2);
        $this->pick($session, $outdoor, 5);

        $wheel = Wheel::forSession($session, weatherProofOnly: true);

        $this->assertSame(
            ['Bowling', 'Team lunch'],
            array_map(fn ($slice) => $slice->activity->name, $wheel->slices),
        );

        // The survivors keep their own weights; dropping the outdoor slice does
        // not redistribute its picks to anyone.
        $this->assertSame(3, $wheel->totalWeight());
        $this->assertSame(1, $wheel->slices[0]->weight);
        $this->assertSame(2, $wheel->slices[1]->weight);
    }

    public function test_indexes_are_renumbered_on_the_weather_proof_wheel(): void
    {
        $session = WellnessSession::factory()->create();
        $outdoor = Activity::factory()->outdoor()->create(['name' => 'Alpha']);
        $indoor = Activity::factory()->indoor()->create(['name' => 'Beta']);

        $this->pick($session, $outdoor, 1);
        $this->pick($session, $indoor, 1);

        $wheel = Wheel::forSession($session, weatherProofOnly: true);

        // Beta is index 1 on the full wheel but index 0 here, because the
        // re-spin draws a fresh wheel for the browser to animate.
        $this->assertSame(0, $wheel->slices[0]->index);
        $this->assertSame('Beta', $wheel->slices[0]->activity->name);
        $this->assertEqualsWithDelta(360.0, $wheel->slices[0]->endAngle, 0.0001);
    }
}
