<?php

namespace App\Models;

use App\Enums\DecisionMethod;
use App\Enums\SessionStatus;
use Carbon\CarbonInterface;
use Database\Factories\WellnessSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * One Monday meeting and the Friday it decides.
 *
 * Deliberately not called Session: Laravel already owns that table name, and
 * an App\Models\Session would shadow the Session facade in every import.
 */
class WellnessSession extends Model
{
    /** @use HasFactory<WellnessSessionFactory> */
    use HasFactory;

    /** Picks each person gets per session. */
    public const PICKS_PER_USER = 3;

    /**
     * Mirrors the database default. Without this a freshly created session
     * would come back with a null status until it was re-read, so isOpen()
     * would answer false on the very row that just opened.
     */
    protected $attributes = [
        'status' => SessionStatus::Open->value,
    ];

    protected $fillable = [
        'meeting_date',
        'status',
        'activity_id',
        'decided_at',
        'decision_method',
        'rained_off_at',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'status' => SessionStatus::class,
            'decision_method' => DecisionMethod::class,
            'decided_at' => 'datetime',
            'rained_off_at' => 'datetime',
        ];
    }

    /**
     * The Friday a session started today would decide.
     *
     * Carbon's next('friday') always moves forward, so on a Friday it would
     * skip a week - hence the explicit check.
     */
    public static function comingFriday(): CarbonInterface
    {
        $today = today();

        return $today->isFriday() ? $today : $today->next(CarbonInterface::FRIDAY);
    }

    /**
     * Open a session for the coming Friday, or hand back the open one if there
     * already is one. Safe to call twice, and safe to call concurrently.
     *
     * The insert gets its own transaction on purpose. Postgres aborts the whole
     * transaction when any statement fails, so catching the unique violation is
     * not enough by itself - every later query would die with "current
     * transaction is aborted". A nested DB::transaction() issues a SAVEPOINT,
     * so the rollback undoes only this insert and leaves any surrounding
     * transaction usable.
     */
    public static function openForComingFriday(): self
    {
        $friday = static::comingFriday();

        try {
            return DB::transaction(fn () => static::create(['meeting_date' => $friday]));
        } catch (UniqueConstraintViolationException) {
            // One of two indexes refused the insert, and both mean "there is
            // already a session to use": either this Friday is taken whatever
            // its status, or an older session is still open. Prefer this
            // Friday's, since that is what the caller asked for.
            //
            // query()->open(), not static::open(): the #[Scope] method exists on
            // the model, so a static call would hit it directly and bypass the
            // builder that turns it into a where clause.
            return static::query()->where('meeting_date', $friday)->first()
                ?? static::query()->open()->sole();
        }
    }

    /** The session the app should be showing: the soonest one not yet past. */
    public static function current(): ?self
    {
        return static::query()
            ->where('meeting_date', '>=', today())
            ->orderBy('meeting_date')
            ->first();
    }

    /** The winning activity. Null until the wheel is spun. */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return HasMany<Pick, $this> */
    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', SessionStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === SessionStatus::Open;
    }

    public function isDecided(): bool
    {
        return $this->status === SessionStatus::Decided;
    }

    public function wasRainedOff(): bool
    {
        return $this->rained_off_at !== null;
    }

    /** How many of their three picks a user has left in this session. */
    public function picksRemainingFor(User $user): int
    {
        return max(0, self::PICKS_PER_USER - $this->picks()->where('user_id', $user->id)->count());
    }

    /**
     * Has everyone had their say? Gates the spin button, which the "spin
     * anyway" override exists to get past when somebody is away.
     *
     * "Everyone" is every user account. There is no is_active flag on users
     * yet, so a departed colleague's account would hold this false forever and
     * leave the team relying on the override. Add the flag when that happens.
     */
    public function everyoneHasPicked(): bool
    {
        return $this->picks()->distinct()->count('user_id') >= User::count();
    }

    /** Users who have not picked anything yet. */
    public function usersYetToPick()
    {
        return User::query()
            ->whereNotIn('id', $this->picks()->select('user_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Call the week off. Returns false if it was already skipped, so callers
     * can say so rather than pretending something happened.
     */
    public function skip(): bool
    {
        if ($this->status === SessionStatus::Skipped) {
            return false;
        }

        $this->forceFill(['status' => SessionStatus::Skipped])->save();

        return true;
    }

    /**
     * Is another week currently holding the open slot?
     *
     * Only one session may be open at a time, so a week cannot be reopened
     * while a different one is still taking picks.
     */
    public function anotherWeekIsOpen(): bool
    {
        return static::query()->open()->whereKeyNot($this->getKey())->exists();
    }

    /**
     * Put the week back to picking, throwing away whatever it had decided.
     *
     * Picks are deliberately left alone - this undoes the spin, not the
     * choosing that led to it, so the same wheel can simply be spun again.
     * It also un-skips a skipped week, which is the only way back from that.
     *
     * Wrapped in its own transaction for the same reason as
     * openForComingFriday(): Postgres aborts the whole transaction when a
     * statement fails, and the partial unique index will refuse this outright
     * if another week claimed the open slot between the check and the write.
     */
    public function reopen(): bool
    {
        if ($this->isOpen()) {
            return false;
        }

        try {
            return DB::transaction(function () {
                $this->forceFill([
                    'status' => SessionStatus::Open,
                    'activity_id' => null,
                    'decided_at' => null,
                    'decision_method' => null,
                    'rained_off_at' => null,
                ])->save();

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race for the open slot. Leave this week exactly as it was.
            $this->refresh();

            return false;
        }
    }

    /**
     * Record the outcome. Used by both the first spin and a rained-off re-spin;
     * the only difference is the rained_off_at stamp.
     */
    public function decide(Activity $activity, DecisionMethod $method, bool $rainedOff = false): void
    {
        $this->forceFill([
            'activity_id' => $activity->id,
            'status' => SessionStatus::Decided,
            'decided_at' => now(),
            'decision_method' => $method,
            'rained_off_at' => $rainedOff ? now() : $this->rained_off_at,
        ])->save();
    }
}
