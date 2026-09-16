<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Activities carry a created_by, so somebody has to exist first.
        // Password is "password" - local development only.
        User::firstOrCreate(
            ['email' => 'admin@traseable.test'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        $this->call([
            CategorySeeder::class,
            ActivitySeeder::class,
        ]);
    }
}
