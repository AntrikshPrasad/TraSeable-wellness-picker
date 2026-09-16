<?php

namespace App\Support;

use App\Models\Activity;

/**
 * One wedge of the wheel.
 *
 * The index is what the spin endpoint hands back to the browser, and what the
 * animation rotates to. It is the position in the wheel's own slice list, not
 * an activity id, so the client never has to work out where anything sits.
 */
final readonly class WheelSlice
{
    public function __construct(
        public int $index,
        public Activity $activity,
        public int $weight,
        public float $startAngle,
        public float $endAngle,
    ) {}

    public function sweep(): float
    {
        return $this->endAngle - $this->startAngle;
    }

    /** The angle to point the pointer at to land on this slice. */
    public function midAngle(): float
    {
        return $this->startAngle + ($this->sweep() / 2);
    }

    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'activity_id' => $this->activity->id,
            'name' => $this->activity->name,
            'color' => $this->activity->category->color,
            'weight' => $this->weight,
            'start_angle' => round($this->startAngle, 4),
            'end_angle' => round($this->endAngle, 4),
        ];
    }
}
