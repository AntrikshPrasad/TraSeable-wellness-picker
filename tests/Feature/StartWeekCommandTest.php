<?php

namespace Tests\Feature;

use App\Enums\DecisionMethod;
use App\Models\Activity;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StartWeekCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_a_session_for_the_coming_friday(): void
    {
        // A Monday, which is when the schedule actually fires it.
        Carbon::setTestNow('2026-09-14 07:00:00');

        $this->artisan('wellness:start-week')
            ->expectsOutputToContain('Opened a session for Friday 18 September')
            ->assertSuccessful();

        $session = WellnessSession::sole();

        $this->assertTrue($session->isOpen());
        $this->assertSame('2026-09-18', $session->meeting_date->toDateString());
    }

    public function test_running_it_twice_does_not_open_a_second_session(): void
    {
        Carbon::setTestNow('2026-09-14 07:00:00');

        $this->artisan('wellness:start-week')->assertSuccessful();

        $this->artisan('wellness:start-week')
            ->expectsOutputToContain('already has a session')
            ->assertSuccessful();

        $this->assertDatabaseCount('wellness_sessions', 1);
    }

    public function test_it_is_a_no_op_even_when_the_open_session_is_for_another_week(): void
    {
        Carbon::setTestNow('2026-09-14 07:00:00');

        // Left over from a week nobody got round to deciding.
        $stale = WellnessSession::factory()->create(['meeting_date' => '2026-09-11']);

        $this->artisan('wellness:start-week')
            ->expectsOutputToContain('already has a session')
            ->assertSuccessful();

        $this->assertDatabaseCount('wellness_sessions', 1);
        $this->assertSame($stale->id, WellnessSession::sole()->id);
    }

    public function test_it_opens_a_new_session_once_the_last_one_was_decided(): void
    {
        Carbon::setTestNow('2026-09-14 07:00:00');

        $user = User::factory()->create();
        $activity = Activity::factory()->for($user, 'creator')->create();

        $lastWeek = WellnessSession::factory()->create(['meeting_date' => '2026-09-11']);
        $lastWeek->decide($activity, DecisionMethod::Spin);

        $this->artisan('wellness:start-week')
            ->expectsOutputToContain('Opened a session for Friday 18 September')
            ->assertSuccessful();

        $this->assertDatabaseCount('wellness_sessions', 2);
        $this->assertSame('2026-09-18', WellnessSession::query()->open()->sole()->meeting_date->toDateString());
    }

    /**
     * The gap the partial index alone left open: it only ever refused a second
     * OPEN session, so once Friday had been decided a second session for the
     * same Friday slipped through, and the app then had two rows competing to
     * be the current one.
     */
    public function test_it_will_not_open_a_second_session_for_a_friday_already_decided(): void
    {
        Carbon::setTestNow('2026-09-14 07:00:00');

        $user = User::factory()->create();
        $activity = Activity::factory()->for($user, 'creator')->create();

        $this->artisan('wellness:start-week')->assertSuccessful();
        WellnessSession::sole()->decide($activity, DecisionMethod::Spin);

        $this->artisan('wellness:start-week')
            ->expectsOutputToContain('Friday 18 September already has a session (status: decided)')
            ->assertSuccessful();

        $this->assertDatabaseCount('wellness_sessions', 1);
    }

    public function test_it_opens_a_new_session_once_the_last_one_was_skipped(): void
    {
        Carbon::setTestNow('2026-09-14 07:00:00');

        WellnessSession::factory()->create(['meeting_date' => '2026-09-11'])->skip();

        $this->artisan('wellness:start-week')->assertSuccessful();

        $this->assertDatabaseCount('wellness_sessions', 2);
    }

    /** Run on a Friday, the session is for that same Friday, not the next one. */
    public function test_running_it_on_a_friday_targets_that_day(): void
    {
        Carbon::setTestNow('2026-09-18 07:00:00');

        $this->artisan('wellness:start-week')->assertSuccessful();

        $this->assertSame('2026-09-18', WellnessSession::sole()->meeting_date->toDateString());
    }

    public function test_it_is_scheduled_for_monday_mornings(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains($event->command ?? '', 'wellness:start-week'));

        $this->assertCount(1, $events, 'The command should be scheduled exactly once.');

        // Minute 0, hour 7, any day of month, any month, Monday.
        $this->assertSame('0 7 * * 1', $events->sole()->expression);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
