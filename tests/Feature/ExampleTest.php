<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_sent_from_home_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
