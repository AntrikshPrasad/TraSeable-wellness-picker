<?php

namespace App\Support;

use App\Enums\Location;
use App\Models\Activity;
use App\Models\WellnessSession;
use LogicException;
use Random\Randomizer;

/**
 * The wheel for one session: every activity with at least one pick, each
 * taking a share of the circle proportional to how many people picked it.
 *
 * Drawing the winner is split in two on purpose. sliceForTicket() is pure
 * arithmetic and can be tested exactly at every boundary; spin() only adds the
 * random number. That keeps the part that decides Friday out of reach of
 * "it passed on my machine".
 */
final readonly class Wheel
{
    /** @param  list<WheelSlice>  $slices */
    private function __construct(public array $slices) {}

    /**
     * @param  bool  $weatherProofOnly  Restrict to indoor/either, for a rained-off re-spin.
     */
    public static function forSession(WellnessSession $session, bool $weatherProofOnly = false): self
    {
        // weight = number of picks. One grouped query rather than counting in PHP.
        $weights = $session->picks()
            ->selectRaw('activity_id, count(*) as weight')
            ->groupBy('activity_id')
            ->pluck('weight', 'activity_id');

        $activities = Activity::query()
            ->whereIn('id', $weights->keys())
            ->when($weatherProofOnly, fn ($query) => $query->whereIn('location', Location::weatherProof()))
            ->with('category')
            // Same stable alphabetical order as the picking list. The order has
            // to be deterministic or the index handed to the browser would point
            // at a different wedge than the one the server chose.
            ->orderBy('name')
            ->get();

        $slices = [];
        $cursor = 0.0;
        $total = (int) $activities->sum(fn (Activity $activity) => $weights[$activity->id]);

        foreach ($activities->values() as $index => $activity) {
            $weight = (int) $weights[$activity->id];
            $sweep = $total > 0 ? ($weight / $total) * 360 : 0.0;

            $slices[] = new WheelSlice(
                index: $index,
                activity: $activity,
                weight: $weight,
                startAngle: $cursor,
                endAngle: $cursor + $sweep,
            );

            $cursor += $sweep;
        }

        return new self($slices);
    }

    public function isEmpty(): bool
    {
        return $this->slices === [];
    }

    public function totalWeight(): int
    {
        return array_sum(array_map(fn (WheelSlice $slice) => $slice->weight, $this->slices));
    }

    /**
     * Draw a winner. The ticket is a number from 1 to the total number of
     * picks, so every pick anybody made is one equally likely ticket - which
     * is what "slice size proportional to pick count" means in practice.
     */
    public function spin(Randomizer $randomizer): WheelSlice
    {
        if ($this->isEmpty()) {
            throw new LogicException('Cannot spin a wheel with no slices.');
        }

        return $this->sliceForTicket($randomizer->getInt(1, $this->totalWeight()));
    }

    /**
     * Which slice holds a given ticket. Walks the slices adding up weights
     * until the running total reaches the ticket.
     */
    public function sliceForTicket(int $ticket): WheelSlice
    {
        $total = $this->totalWeight();

        if ($ticket < 1 || $ticket > $total) {
            throw new LogicException("Ticket {$ticket} is outside the range 1-{$total}.");
        }

        $seen = 0;

        foreach ($this->slices as $slice) {
            $seen += $slice->weight;

            if ($ticket <= $seen) {
                return $slice;
            }
        }

        throw new LogicException('Ticket fell through every slice, which should be impossible.');
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn (WheelSlice $slice) => $slice->toArray(), $this->slices);
    }
}
