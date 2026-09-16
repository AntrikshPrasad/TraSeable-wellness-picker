<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Open the week's session before the Monday meeting.
 *
 * The time is in the application timezone (config/app.php, set from
 * APP_TIMEZONE), NOT the server's. Get that wrong and the session opens after
 * the meeting it exists for - on a UTC server with a team twelve hours ahead,
 * 07:00 Monday lands on Monday evening for them.
 *
 * withoutOverlapping is belt and braces: the partial unique index already
 * refuses a second open session, but there is no reason to have two of these
 * racing at all.
 */
Schedule::command('wellness:start-week')
    ->mondays()
    ->at('07:00')
    ->withoutOverlapping();
