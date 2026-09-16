<?php

namespace App\Models;

use App\Enums\Location;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /** An activity counts as "new" on the picking screen for this long. */
    public const NEW_FOR_DAYS = 14;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'location',
        'duration_minutes',
        'min_people',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'location' => Location::class,
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
            'min_people' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Pick, $this> */
    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    /** Weeks this activity won. @return HasMany<WellnessSession, $this> */
    public function decidedSessions(): HasMany
    {
        return $this->hasMany(WellnessSession::class);
    }

    /**
     * Can this be erased rather than retired?
     *
     * Only when nothing points at it. picks cascade on delete and a session's
     * activity_id is nulled, so erasing something with history would quietly
     * rewrite it - a past Friday would forget what the team actually did.
     * Anything with a history gets retired instead, which is what is_active is
     * for.
     */
    public function canBeErased(): bool
    {
        $picks = $this->picks_count ?? $this->picks()->count();
        $sessions = $this->decided_sessions_count ?? $this->decidedSessions()->count();

        return $picks === 0 && $sessions === 0;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Activities that work in a given setting.
     *
     * Asking for indoor has to return the "either" ones too - they work indoors
     * as much as anything does. Filtering on equality alone would quietly hide
     * half the pool.
     */
    #[Scope]
    protected function fitsLocation(Builder $query, Location $location): void
    {
        $query->whereIn('location', array_unique([$location, Location::Either], SORT_REGULAR));
    }

    /**
     * Activities that fit in the time available.
     *
     * A null duration means nobody recorded one, not that it takes forever, so
     * those stay in - being excluded by a field nobody filled in would be
     * worse than being offered something that might overrun.
     */
    #[Scope]
    protected function fitsWithin(Builder $query, int $minutes): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('duration_minutes')
            ->orWhere('duration_minutes', '<=', $minutes));
    }

    /** Activities a group of this size can actually do. Null means any number. */
    #[Scope]
    protected function worksWith(Builder $query, int $people): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('min_people')
            ->orWhere('min_people', '<=', $people));
    }

    /** Drives the neutral "New" badge that stands in for a zero pick count. */
    public function isNew(): bool
    {
        return $this->created_at?->gt(now()->subDays(self::NEW_FOR_DAYS)) ?? false;
    }

    public function isWeatherProof(): bool
    {
        return in_array($this->location, Location::weatherProof(), strict: true);
    }
}
