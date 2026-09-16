<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Random\Randomizer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolved from the container rather than called statically so a test
        // can swap in a seeded engine and get a repeatable sequence of spins.
        // The default engine is the platform CSPRNG, which is what production
        // wants and what a test can do nothing with.
        $this->app->bind(Randomizer::class, fn () => new Randomizer);
    }

    public function boot(): void
    {
        //
    }
}
