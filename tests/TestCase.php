<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // These tests assert Inertia props and status codes, never asset URLs
        // — but rendering app.blade.php runs @vite, which needs a manifest that
        // only exists after `npm run build`. That made the suite pass or fail
        // depending on whether the person running it had happened to build the
        // frontend first, which is not a property a test suite should have.
        $this->withoutVite();

        // Feature tests inspect Laravel responses without a renderer process.
        // A previously built SSR bundle must not introduce outbound HTTP.
        config(['inertia.ssr.enabled' => false]);
    }
}
