<?php

namespace App\Console\Commands;

use App\Models\WellnessSession;
use Illuminate\Console\Command;

/**
 * Opens the week's picking session. Scheduled for Monday morning in
 * routes/console.php, and safe to run by hand at any time.
 *
 * The "only one open session" rule is a partial unique index on the table
 * rather than a check in here, so running this twice - by the schedule, by
 * hand, or by two servers at once - cannot produce a second open session.
 */
class StartWeek extends Command
{
    protected $signature = 'wellness:start-week';

    protected $description = 'Open a picking session for the coming Friday (no-op if one is already open)';

    public function handle(): int
    {
        $session = WellnessSession::openForComingFriday();
        $friday = $session->meeting_date->format('l j F');

        if ($session->wasRecentlyCreated) {
            $this->components->info("Opened a session for {$friday}.");
        } else {
            $this->components->info("Nothing to do: {$friday} already has a session (status: {$session->status->value}).");
        }

        return self::SUCCESS;
    }
}
