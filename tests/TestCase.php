<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Run central migrations for all tests
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => 'database/migrations']);
    }
}
