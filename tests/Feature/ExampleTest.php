<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_week_screen_requires_signing_in(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
