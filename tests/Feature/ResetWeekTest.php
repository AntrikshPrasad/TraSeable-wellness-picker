<?php

namespace Tests\Feature;

use App\Enums\DecisionMethod;
use App\Enums\SessionStatus;
use App\Livewire\ActivityPicker;
use App\Models\Activity;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResetWeekTest extends TestCase
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

    /** A decided session with one pick on it, ready to be reset. */
    private function decidedSession(User $user): array
    {
        $session = WellnessSession::factory()->create();
        $activity = $this->activityBy($user, ['name' => 'Bowling']);

        $this->pick($session, $activity, $user);
        $session->decide($activity, DecisionMethod::Spin);

        return [$session, $activity];
    }

    public function test_resetting_returns_the_week_to_picking(): void
    {
        $user = User::factory()->create();
        [$session] = $this->decidedSession($user);

        $this->actingAs($user)
            ->postJson(route('sessions.reset', $session))
            ->assertOk()
            ->assertJsonPath('status', SessionStatus::Open->value);

        $session->refresh();

        $this->assertTrue($session->isOpen());
        $this->assertNull($session->activity_id);
        $this->assertNull($session->decided_at);
        $this->assertNull($session->decision_method);
    }

    /** The point of the feature: the same wheel, ready to spin again. */
    public function test_resetting_keeps_everyones_picks(): void
    {
        $user = User::factory()->create();
        [$session, $activity] = $this->decidedSession($user);

        $other = User::factory()->create();
        $this->pick($session, $this->activityBy($user, ['name' => 'Team lunch']), $other);

        $this->actingAs($user)->postJson(route('sessions.reset', $session))->assertOk();

        $this->assertDatabaseCount('picks', 2);
        $this->assertDatabaseHas('picks', [
            'wellness_session_id' => $session->id,
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_the_week_can_be_spun_again_after_a_reset(): void
    {
        $user = User::factory()->create();
        [$session, $activity] = $this->decidedSession($user);

        $this->actingAs($user)->postJson(route('sessions.reset', $session))->assertOk();

        $this->actingAs($user)
            ->postJson(route('sessions.spin', $session))
            ->assertOk()
            ->assertJsonPath('activity.name', 'Bowling');

        $this->assertSame($activity->id, $session->fresh()->activity_id);
    }

    public function test_resetting_clears_a_rained_off_stamp(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $indoor = Activity::factory()->indoor()->for($user, 'creator')->create();

        $this->pick($session, $indoor, $user);
        $session->decide($indoor, DecisionMethod::Spin, rainedOff: true);

        $this->assertNotNull($session->fresh()->rained_off_at);

        $this->actingAs($user)->postJson(route('sessions.reset', $session))->assertOk();

        // Otherwise the next result would carry a rained-off note it never earned.
        $this->assertNull($session->fresh()->rained_off_at);
    }

    public function test_a_skipped_week_can_be_reopened(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $this->pick($session, $this->activityBy($user), $user);
        $session->skip();

        $this->actingAs($user)
            ->postJson(route('sessions.reset', $session))
            ->assertOk()
            ->assertJsonPath('status', SessionStatus::Open->value);

        $this->assertTrue($session->fresh()->isOpen());
        $this->assertDatabaseCount('picks', 1);
    }

    public function test_resetting_an_open_week_is_refused(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();

        $this->actingAs($user)
            ->postJson(route('sessions.reset', $session))
            ->assertConflict()
            ->assertJsonPath('message', 'This week has not been decided yet, so there is nothing to reset.');
    }

    /**
     * Only one session may be open at a time. On a Wednesday with next week
     * already started, resetting this week would want a second open slot.
     */
    public function test_resetting_is_refused_while_another_week_is_open(): void
    {
        $user = User::factory()->create();
        [$thisWeek] = $this->decidedSession($user);

        $nextWeek = WellnessSession::factory()->create([
            'meeting_date' => $thisWeek->meeting_date->copy()->addWeek(),
        ]);

        $this->assertTrue($nextWeek->isOpen());

        $this->actingAs($user)
            ->postJson(route('sessions.reset', $thisWeek))
            ->assertConflict()
            ->assertJsonPath('message', 'Another week is already open for picking. Decide or skip that one first.');

        $this->assertTrue($thisWeek->fresh()->isDecided());
    }

    public function test_resetting_requires_signing_in(): void
    {
        $user = User::factory()->create();
        [$session] = $this->decidedSession($user);

        $this->postJson(route('sessions.reset', $session))->assertUnauthorized();

        $this->assertTrue($session->fresh()->isDecided());
    }

    public function test_the_result_screen_offers_a_reset(): void
    {
        $user = User::factory()->create();
        [$session] = $this->decidedSession($user);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee("Reset this week's spin", escape: false)
            ->call('resetWeek')
            ->assertSee('Bowling');

        $this->assertTrue($session->fresh()->isOpen());
    }

    public function test_resetting_from_the_screen_restores_the_users_own_picks(): void
    {
        $user = User::factory()->create();
        [$session, $activity] = $this->decidedSession($user);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->call('resetWeek')
            // Back on the picking screen with their pick still ticked.
            ->assertSet('picks', [$activity->id]);
    }

    public function test_the_screen_explains_why_a_reset_was_refused(): void
    {
        $user = User::factory()->create();
        [$thisWeek] = $this->decidedSession($user);

        WellnessSession::factory()->create([
            'meeting_date' => $thisWeek->meeting_date->copy()->addWeek(),
        ]);

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->call('resetWeek')
            ->assertSet('notice', 'Another week is already open for picking. Decide or skip that one first.')
            ->assertSee('Another week is already open');

        $this->assertTrue($thisWeek->fresh()->isDecided());
    }

    public function test_the_skipped_screen_offers_a_way_back(): void
    {
        $user = User::factory()->create();
        $session = WellnessSession::factory()->create();
        $session->skip();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertSee('Reopen this week')
            ->call('resetWeek');

        $this->assertTrue($session->fresh()->isOpen());
    }

    public function test_the_picking_screen_does_not_offer_a_reset(): void
    {
        $user = User::factory()->create();
        WellnessSession::factory()->create();

        Livewire::actingAs($user)
            ->test(ActivityPicker::class)
            ->assertDontSee("Reset this week's spin", escape: false)
            ->assertDontSee('Reopen this week');
    }
}
