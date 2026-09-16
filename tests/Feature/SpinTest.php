<?php

namespace Tests\Feature;

use App\Enums\DecisionMethod;
use App\Enums\Location;
use App\Enums\SessionStatus;
use App\Models\Activity;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class SpinTest extends TestCase
{
    use RefreshDatabase;

    private function seedRandomizer(int $seed = 20260915): void
    {
        $this->swap(Randomizer::class, new Randomizer(new Mt19937($seed)));
    }

    private function pick(WellnessSession $session, Activity $activity, User $user): void
    {
        Pick::create([
            'wellness_session_id' => $session->id,
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);
    }

    /**
     * Activities need a creator, and letting the factory invent one would quietly
     * add a person to the team - which is exactly what everyoneHasPicked() counts.
     */
    private function activityBy(User $creator, array $attributes = []): Activity
    {
        return Activity::factory()->for($creator, 'creator')->create($attributes);
    }

    public function test_spinning_requires_signing_in(): void
    {
        $session = WellnessSession::factory()->create();

        $this->postJson(route('sessions.spin', $session))->assertUnauthorized();
    }

    public function test_a_spin_decides_the_session_and_returns_the_winning_index(): void
    {
        $this->seedRandomizer();

        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $activity = $this->activityBy($user, ['name' => 'Bowling']);

        $this->pick($session, $activity, $user);

        $response = $this->actingAs($user)
            ->postJson(route('sessions.spin', $session))
            ->assertOk()
            ->assertJsonPath('status', SessionStatus::Decided->value)
            ->assertJsonPath('winning_index', 0)
            ->assertJsonPath('activity.name', 'Bowling')
            ->assertJsonPath('rained_off', false);

        // The slice list travels with the result so the browser draws exactly
        // the wheel the index refers to.
        $response->assertJsonCount(1, 'slices')
            ->assertJsonPath('slices.0.weight', 1);

        $session->refresh();

        $this->assertSame(SessionStatus::Decided, $session->status);
        $this->assertSame($activity->id, $session->activity_id);
        $this->assertSame(DecisionMethod::Spin, $session->decision_method);
        $this->assertNotNull($session->decided_at);
        $this->assertNull($session->rained_off_at);
    }

    public function test_the_winner_is_always_something_that_was_picked(): void
    {
        $this->seedRandomizer();

        $users = User::factory()->count(3)->create();
        $session = WellnessSession::factory()->create();

        $picked = Activity::factory()->for($users->first(), 'creator')->count(2)->create();
        $neverPicked = $this->activityBy($users->first(), ['name' => 'Nobody wanted this']);

        foreach ($users as $index => $user) {
            $this->pick($session, $picked[$index % 2], $user);
        }

        $this->actingAs($users->first())
            ->postJson(route('sessions.spin', $session))
            ->assertOk();

        $this->assertNotSame($neverPicked->id, $session->fresh()->activity_id);
        $this->assertContains($session->fresh()->activity_id, $picked->pluck('id')->all());
    }

    public function test_a_second_spin_is_refused(): void
    {
        $this->seedRandomizer();

        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $this->pick($session, $this->activityBy($user), $user);

        $this->actingAs($user)->postJson(route('sessions.spin', $session))->assertOk();

        $first = $session->fresh();

        $this->actingAs($user)
            ->postJson(route('sessions.spin', $session))
            ->assertConflict()
            ->assertJsonPath('message', 'This session has already been decided.');

        // Nothing moved on the second attempt.
        $this->assertEquals($first->activity_id, $session->fresh()->activity_id);
        $this->assertEquals($first->decided_at, $session->fresh()->decided_at);
    }

    public function test_spinning_is_refused_while_someone_has_not_picked(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $session = WellnessSession::factory()->create();

        $this->pick($session, $this->activityBy($alice), $alice);

        $this->actingAs($alice)
            ->postJson(route('sessions.spin', $session))
            ->assertConflict()
            ->assertJsonPath('message', 'Not everyone has picked yet.')
            ->assertJsonPath('waiting_on', ['Bob']);

        $this->assertTrue($session->fresh()->isOpen());
    }

    public function test_spin_anyway_overrides_the_waiting_check(): void
    {
        $this->seedRandomizer();

        $alice = User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob who is away']);
        $session = WellnessSession::factory()->create();

        $this->pick($session, $this->activityBy($alice), $alice);

        $this->actingAs($alice)
            ->postJson(route('sessions.spin', $session), ['force' => true])
            ->assertOk();

        $this->assertTrue($session->fresh()->isDecided());
    }

    public function test_spinning_with_no_picks_at_all_is_refused(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $this->actingAs($user)
            ->postJson(route('sessions.spin', $session), ['force' => true])
            ->assertConflict()
            ->assertJsonPath('message', 'Nobody has picked anything yet.');

        $this->assertTrue($session->fresh()->isOpen());
    }

    public function test_rained_off_re_spins_among_the_weather_proof_picks(): void
    {
        $this->seedRandomizer();

        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $outdoor = Activity::factory()->outdoor()->for($user, 'creator')->create(['name' => 'Coastal walk']);
        $indoor = Activity::factory()->indoor()->for($user, 'creator')->create(['name' => 'Bowling']);

        $this->pick($session, $outdoor, $user);
        $this->pick($session, $indoor, User::factory()->create());

        $session->decide($outdoor, DecisionMethod::Spin);

        $this->actingAs($user)
            ->postJson(route('sessions.rained-off', $session))
            ->assertOk()
            ->assertJsonPath('activity.name', 'Bowling')
            ->assertJsonPath('rained_off', true);

        $session->refresh();

        $this->assertSame($indoor->id, $session->activity_id);
        $this->assertNotNull($session->rained_off_at);
        $this->assertTrue($session->isDecided());
    }

    public function test_an_either_activity_survives_the_rain(): void
    {
        $this->seedRandomizer();

        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $outdoor = Activity::factory()->outdoor()->for($user, 'creator')->create(['name' => 'Coastal walk']);
        $either = $this->activityBy($user, [
            'name' => 'Team lunch',
            'location' => Location::Either,
        ]);

        $this->pick($session, $outdoor, $user);
        $this->pick($session, $either, User::factory()->create());

        $session->decide($outdoor, DecisionMethod::Spin);

        $this->actingAs($user)
            ->postJson(route('sessions.rained-off', $session))
            ->assertOk()
            ->assertJsonPath('activity.name', 'Team lunch');
    }

    public function test_rained_off_is_refused_when_nothing_picked_works_indoors(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $outdoor = Activity::factory()->outdoor()->for($user, 'creator')->create();

        $this->pick($session, $outdoor, $user);
        $session->decide($outdoor, DecisionMethod::Spin);

        $this->actingAs($user)
            ->postJson(route('sessions.rained-off', $session))
            ->assertConflict()
            ->assertJsonPath('message', 'Nobody picked anything that works indoors.');

        $this->assertNull($session->fresh()->rained_off_at);
        $this->assertSame($outdoor->id, $session->fresh()->activity_id);
    }

    public function test_rained_off_is_refused_on_a_session_that_was_never_decided(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $this->pick($session, Activity::factory()->indoor()->for($user, 'creator')->create(), $user);

        $this->actingAs($user)
            ->postJson(route('sessions.rained-off', $session))
            ->assertConflict()
            ->assertJsonPath('message', 'Only a decided session can be rained off.');
    }

    public function test_a_session_can_be_skipped(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $this->actingAs($user)
            ->postJson(route('sessions.skip', $session))
            ->assertOk()
            ->assertJsonPath('status', SessionStatus::Skipped->value);

        $this->assertSame(SessionStatus::Skipped, $session->fresh()->status);
    }

    public function test_skipping_frees_the_open_slot_for_the_following_week(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $this->actingAs($user)->postJson(route('sessions.skip', $session))->assertOk();

        // The partial unique index only constrains open rows, so a skipped week
        // must not block the next one from opening.
        Carbon::setTestNow($session->meeting_date->copy()->addDay());
        $next = WellnessSession::openForComingFriday();

        $this->assertTrue($next->isOpen());
        $this->assertNotSame($session->id, $next->id);
        $this->assertTrue($next->meeting_date->gt($session->meeting_date));

        Carbon::setTestNow();
    }

    /**
     * Skipping does not make room for a *second* session on the same Friday -
     * one session per Friday is a unique index on meeting_date. Changing your
     * mind means reopening that session, not creating a rival to it.
     */
    public function test_skipping_does_not_allow_a_rival_session_on_the_same_friday(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create([
            'meeting_date' => WellnessSession::comingFriday(),
        ]);

        $this->actingAs($user)->postJson(route('sessions.skip', $session))->assertOk();

        $same = WellnessSession::openForComingFriday();

        $this->assertSame($session->id, $same->id);
        $this->assertDatabaseCount('wellness_sessions', 1);
    }

    /**
     * Same picks, same seed, same winner - across two separate sessions.
     * This repeatability is what makes the weighting testable at all, so it is
     * worth asserting rather than assuming.
     */
    public function test_a_seeded_spin_is_repeatable_across_sessions(): void
    {
        $user = User::factory()->create();

        $activities = collect(['Alpha', 'Beta', 'Gamma', 'Delta'])
            ->map(fn (string $name) => $this->activityBy($user, ['name' => $name]));

        $winners = [];

        foreach ([1, 2] as $run) {
            $session = WellnessSession::factory()->create();

            foreach ($activities as $activity) {
                $this->pick($session, $activity, $user);
            }

            $this->seedRandomizer(4242);

            $winners[] = $this->actingAs($user)
                ->postJson(route('sessions.spin', $session), ['force' => true])
                ->assertOk()
                ->json('activity.name');
        }

        $this->assertSame($winners[0], $winners[1]);
    }
}
