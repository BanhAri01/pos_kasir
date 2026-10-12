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
