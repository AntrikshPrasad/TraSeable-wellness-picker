<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Pick;
use App\Models\User;
use App\Models\WellnessSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pick> */
class PickFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wellness_session_id' => WellnessSession::factory(),
            'user_id' => User::factory(),
            'activity_id' => Activity::factory(),
        ];
    }
}
