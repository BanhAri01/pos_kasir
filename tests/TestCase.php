<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Data acuan (role, modul, jenis usaha) di-seed untuk setiap test. */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Test tidak butuh hasil build Vite.
        $this->withoutVite();
    }
}
