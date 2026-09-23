<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home page is the public landing page for guests (see HomeTest).
     */
    public function test_the_home_page_loads(): void
    {
        $this->get('/')->assertOk();
    }
}
