<?php

namespace Database\Factories;

use App\Enums\Location;
use App\Models\Activity;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Activity> */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, asText: true),
            'description' => fake()->optional()->sentence(),
            'category_id' => Category::factory(),
            'location' => fake()->randomElement(Location::cases()),
            'duration_minutes' => fake()->optional()->randomElement([30, 45, 60, 90, 120]),
            'min_people' => fake()->optional()->numberBetween(2, 8),
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }

    public function retired(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function indoor(): static
    {
        return $this->state(['location' => Location::Indoor]);
    }

    public function outdoor(): static
    {
        return $this->state(['location' => Location::Outdoor]);
    }
}
