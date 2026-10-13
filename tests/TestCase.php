<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against a cached config.
     *
     * phpunit.xml sets APP_URL to http://localhost, because this app is served
     * from a sub-path and route() would otherwise build URLs the test client
     * cannot reach. A cached config is baked at the moment it was written and
     * ignores those env overrides entirely, so every route-based test 404s --
     * all at once, with nothing to suggest why. It cost a full debugging pass
     * once already.
     *
     * `composer test` clears the config first; `php artisan test` does not,
     * which is the trap. One clear failure is worth more than 300 silent ones.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->configurationIsCached()) {
            throw new RuntimeException(
                'The config is cached, so phpunit.xml cannot override APP_URL and '
                .'every route-based test would 404. Run: php artisan config:clear'
            );
        }
    }
}
