<?php

namespace App\Models;

use Database\Factories\PickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person putting one activity on the wheel for one session. */
class Pick extends Model
{
    /** @use HasFactory<PickFactory> */
    use HasFactory;

    protected $fillable = ['wellness_session_id', 'user_id', 'activity_id'];

    /** @return BelongsTo<WellnessSession, $this> */
    public function wellnessSession(): BelongsTo
    {
        return $this->belongsTo(WellnessSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
