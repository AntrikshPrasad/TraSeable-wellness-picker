<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The five categories are fixed and not user-creatable, so they live here
 * rather than behind an admin screen. updateOrCreate keyed on name means
 * re-running the seeder recolours rather than duplicates.
 */
class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'physical' => '#16a34a',
        'games' => '#7c3aed',
        'food' => '#ea580c',
        'social' => '#0ea5e9',
        'creative' => '#db2777',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $color) {
            Category::updateOrCreate(['name' => $name], ['color' => $color]);
        }
    }
}
