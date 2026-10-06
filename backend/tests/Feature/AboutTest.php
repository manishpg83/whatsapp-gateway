<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_about_page_shows_the_company_details(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>About Us - InstaMessage</title>', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('BriskBrain Technologies')
            ->assertSee('Office E-1205, Ganesh Glory 11, Jagatpur Road')
            ->assertSee('Ahmedabad, Gujarat 382470')
            ->assertSee('href="tel:+919428889935"', false)
            ->assertSee('+91 94288 89935')
            ->assertSee('href="https://briskbraintech.com/"', false)
            ->assertSee('"@type":"AboutPage"', false)
            ->assertSee('"@type":"PostalAddress"', false);
    }

    public function test_the_about_page_is_linked_and_in_the_sitemap(): void
    {
        $this->get('/')->assertSee('href="'.route('about').'"', false)
            ->assertSee('"telephone":"+919428889935"', false)
            ->assertSee('"addressLocality":"Ahmedabad"', false);

        $this->get('/sitemap.xml')->assertSee('<loc>'.route('about').'</loc>', false);
    }
}
