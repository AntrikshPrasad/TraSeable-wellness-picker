# Build brief: TraSeable wellness picker

## What this is

An internal web app for a team of under 10 people. Every Monday the team meets in person and decides what wellness activity they'll do on Friday. Right now that decision takes too long and defaults to the same few options.

The app replaces the discussion with a mechanic: each person picks three activities from a shared pool, the wheel is built from those picks with slice size proportional to pick count, and one spin decides Friday.

This is a learning project. The developer is new to Laravel, Postgres, and Linux hosting. Favour idiomatic, framework-native solutions over clever ones, and explain the reasoning on anything non-obvious.

## Stack

- Laravel 13
- PostgreSQL
- Livewire for the interactive screens
- Blade + Tailwind for layout
- Laravel Breeze for auth
- Deployed to an Ubuntu VM (nginx + PHP-FPM), set up manually

## Data model

**users** — Breeze default. Self-registration disabled; accounts created by an admin.

**categories** — `id`, `name`, `color`. Fixed seeded set: physical, games, food, social, creative. Not user-creatable.

**activities** — `id`, `name`, `description` (nullable), `category_id`, `location` (enum: indoor / outdoor / either), `created_by` (FK users), `is_active` (bool, soft-delete style), timestamps.

**sessions** — `id`, `meeting_date`, `status` (enum: open / decided / skipped), `activity_id` (nullable FK), `decided_at` (nullable), `decision_method` (enum: spin / manual, nullable), timestamps.

**picks** — `id`, `session_id`, `user_id`, `activity_id`, timestamps. Unique composite index on (`session_id`, `user_id`, `activity_id`) so nobody can pick the same activity twice to inflate its weight.

Relationships: `Activity belongsTo Category`, `Activity hasMany Picks`, `Session hasMany Picks`, `Session belongsTo Activity` (the result), `User hasMany Picks`.

## Core flows

### 1. Session opens

A scheduled artisan command runs Monday morning and creates a new session with `status = open` and `meeting_date` set to the coming Friday. Also expose a manual "start this week" button as a fallback.

Only one open session at a time. If one already exists, the command is a no-op.

### 2. Picking

Each user gets three picks per session. Enforce in application logic (a `picks()->where('session_id', ...)->count() < 3` guard), backed by the unique index.

### 3. Spinning

`POST /sessions/{session}/spin`.

Server-side logic:
- Gather all activities with at least one pick in this session
- Build a weighted pool where weight = number of picks
- Pick a winner randomly, respecting weights
- Write `activity_id`, `decided_at`, `decision_method = 'spin'`, set `status = 'decided'`
- Return the winning activity plus its index/position on the wheel

The client animates the wheel to land on the returned index. The client must never choose the winner.

The spin button is disabled until every active user has made at least one pick, with a "spin anyway" override link for when someone is away.

Once `status = 'decided'`, the spin endpoint rejects further spins. Provide one separate action, "rained off", which re-spins among picked activities where `location` is indoor or either, and records that it happened.

Also allow marking a session `skipped`.

## Screen 1: picking

Mobile-first. People use this on their phones in the Monday meeting.

Header shows the Friday date and a countdown counter ("1 pick left", not "2 of 3 used"), plus a three-segment progress bar.

Category filter chips below the header. "All" is the default.

Then the activity list. Each row shows:
- Selection indicator (check when picked, empty circle when not)
- Activity name
- Sub-line: location icon + indoor/outdoor, plus duration or people requirement
- Live pick count, displayed as small stacked avatars of who picked it
- Activities added within the last 14 days show a neutral "New" badge instead of a zero count

Behaviour:
- The whole row is the tap target. Tapping a picked row un-picks it.
- Counts and avatars update live via Livewire polling or events.
- Sort order must be stable (alphabetical or grouped by category). Do NOT sort by pick count — reordering by popularity creates a feedback loop where top-of-list items get picked because they're top of list.
- When a user has spent all three picks, unpicked rows drop to ~50% opacity but stay tappable. Tapping one shows an inline message explaining they need to un-pick something first. Don't use a disabled state.

At the bottom, an inline "Add an activity" control. Name, category, location. Adding an activity automatically spends one of the adder's picks.

## Screen 2: result

Same route, different state. This is what the app shows from Monday afternoon until Friday, so it's the screen people see most.

- Winning activity name, large
- Friday date and location/duration
- Avatars of everyone who picked it
- Runner-ups listed underneath with their pick counts
- Small note if the result came from a "rained off" re-spin

## Out of scope for v1

Do not build: attendance tracking, points, streaks, leaderboards, photos, comments, mood check-ins, Slack notifications, activity cooldowns.

Cooldowns specifically are unnecessary — the pick weighting is a self-correcting popularity mechanism. If people tire of an activity they stop picking it and its slice shrinks to nothing.

Slack notifications are planned for a second iteration. Don't build them, but don't architect in a way that makes them painful to add.

## Build order

1. Migrations, models, relationships, factories
2. Seeders — categories, plus placeholder activities (the real list is pending from the team; seed 12–15 dummy entries so the wheel is testable)
3. Breeze auth, registration route disabled
4. Livewire picking component: `picks` collection, `toggle($activityId)` method, all UI state derived from that one array
5. Spin endpoint with weighted selection, plus tests for the weighting
6. Wheel rendering and animation
7. Result screen
8. Scheduled Monday command
9. Deploy

## Deployment notes

Manual setup on Ubuntu: nginx, PHP-FPM, Postgres, Composer, Node for asset building, Let's Encrypt.

Watch for Postgres peer authentication — `psql` working as the `postgres` system user while the app can't connect is the classic first-deploy failure. Create a dedicated database role with a password and use `md5`/`scram` auth in `pg_hba.conf`.

Set up a deploy script (pull, `composer install --no-dev`, `php artisan migrate --force`, build assets, restart PHP-FPM) rather than doing steps by hand each time.

Decide early whether the app is publicly reachable behind a login or restricted at the network level, since it affects nginx and TLS setup.
