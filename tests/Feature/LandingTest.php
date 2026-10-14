<?php

use Inertia\Testing\AssertableInertia as Assert;

describe('landing page saasposkasir', function () {
    it('menampilkan landing page hermes pos dengan benar untuk pengunjung/tamu', function () {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing')
                ->has('catalog')
                ->has('trialDays')
            );
    });
});

it('robots dan sitemap memakai domain aplikasi, bukan localhost', function () {
    config(['app.url' => 'http://pos.example.com']);
    \Illuminate\Support\Facades\URL::forceRootUrl('http://pos.example.com');

    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: http://pos.example.com/sitemap.xml', false)->assertSee('Disallow: /', false);
    $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>http://pos.example.com</loc>', false)->assertDontSee('localhost');
});

it('landing boleh diindeks dan tanpa rating palsu', function () {
    $this->get('/')->assertDontSee('noindex', false)->assertDontSee('aggregateRating')->assertDontSee('reviewCount');
});
