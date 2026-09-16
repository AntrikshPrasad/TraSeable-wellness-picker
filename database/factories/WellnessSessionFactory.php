<?php

namespace Database\Factories;

use App\Enums\DecisionMethod;
use App\Enums\SessionStatus;
use App\Models\Activity;
use App\Models\WellnessSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WellnessSession> */
class WellnessSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Successive sessions land on successive Fridays. meeting_date is
            // unique, so without the offset a test that creates two default
            // sessions would collide on the coming Friday.
            'meeting_date' => WellnessSession::comingFriday()
                ->addWeeks(WellnessSession::count())
                ->toDateString(),
            'status' => SessionStatus::Open,
            'activity_id' => null,
            'decided_at' => null,
            'decision_method' => null,
            'rained_off_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => SessionStatus::Open]);
    }

    public function decided(?Activity $activity = null): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Decided,
            'activity_id' => $activity?->id ?? Activity::factory(),
            'decided_at' => now(),
            'decision_method' => DecisionMethod::Spin,
        ]);
    }

    public function skipped(): static
    {
        return $this->state(['status' => SessionStatus::Skipped]);
    }

    public function rainedOff(): static
    {
        return $this->decided()->state(['rained_off_at' => now()]);
    }
}
