<?php

namespace Database\Seeders;

use App\Enums\Location;
use App\Models\Activity;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * PLACEHOLDER DATA. The team's real activity list is still pending; these 14
 * exist so the wheel has something to spin on. Replace wholesale when the real
 * list arrives - nothing else depends on these names.
 */
class ActivitySeeder extends Seeder
{
    private const ACTIVITIES = [
        // [name, category, location, duration_minutes, min_people]
        ['Bowling', 'games', Location::Indoor, 90, 4],
        ['Board game afternoon', 'games', Location::Indoor, 120, 3],
        ['Escape room', 'games', Location::Indoor, 90, 4],
        ['Coastal walk', 'physical', Location::Outdoor, 90, null],
        ['Beach volleyball', 'physical', Location::Outdoor, 60, 6],
        ['Yoga session', 'physical', Location::Either, 60, null],
        ['Table tennis tournament', 'physical', Location::Indoor, 60, 4],
        ['Team lunch', 'food', Location::Either, 90, null],
        ['Bake-off', 'food', Location::Indoor, 120, 3],
        ['Coffee crawl', 'food', Location::Outdoor, 90, null],
        ['Trivia quiz', 'social', Location::Either, 60, 4],
        ['Volunteering afternoon', 'social', Location::Outdoor, 180, null],
        ['Pottery class', 'creative', Location::Indoor, 120, 2],
        ['Photo walk', 'creative', Location::Outdoor, 90, null],
    ];

    public function run(): void
    {
        $categories = Category::pluck('id', 'name');
        $creator = User::query()->orderBy('id')->firstOrFail();

        foreach (self::ACTIVITIES as [$name, $category, $location, $duration, $minPeople]) {
            Activity::updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$category],
                    'location' => $location,
                    'duration_minutes' => $duration,
                    'min_people' => $minPeople,
                    'created_by' => $creator->id,
                    'is_active' => true,
                ],
            );
        }
    }
}
