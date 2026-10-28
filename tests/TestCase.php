<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Fail fast when the test bootstrap would hit a real database.
     *
     * A cached config (bootstrap/cache/config.php) bypasses phpunit.xml
     * environment overrides, which previously ran RefreshDatabase against
     * the production PostgreSQL database. Check before any database trait
     * (RefreshDatabase) gets a chance to migrate/truncate anything.
     */
    protected function setUpTraits()
    {
        $environment = $this->app?->environment();
        $connection = config('database.default');

        if ($environment !== 'testing' || $connection !== 'sqlite') {
            throw new \RuntimeException(
                'Refusing to run tests against environment ['.$environment.'] and database ['.$connection.']. '
                .'Run "php artisan config:clear" so phpunit.xml can force testing + sqlite.'
            );
        }

        parent::setUpTraits();
    }
}
