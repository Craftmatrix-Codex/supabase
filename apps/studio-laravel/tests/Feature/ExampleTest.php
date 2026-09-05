<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_studio_shell_requires_authentication(): void
    {
        $this->get('/')->assertUnauthorized();
        $this->withBasicAuth('studio', 'password')->get('/')->assertOk();
    }
}
