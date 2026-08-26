<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DemoTest extends TestCase
{
    public function test_it_renders_and_reports_that_it_is_unconfigured(): void
    {
        config(['prism.providers.groq.api_key' => '']);

        $this->get('/demo')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Demo')
                ->where('configured', false)
                ->has('model'));
    }

    public function test_it_refuses_to_run_when_no_key_is_set(): void
    {
        // A demo with no credential should say so, not attempt the call and
        // surface whatever the provider returns for an empty bearer token.
        config(['prism.providers.groq.api_key' => '']);

        $this->postJson('/demo', ['prompt' => 'hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'This demo is not configured. Set GROQ_API_KEY to enable it.');
    }

    public function test_it_requires_a_prompt(): void
    {
        $this->postJson('/demo', [])->assertStatus(422)->assertJsonValidationErrors('prompt');
    }

    public function test_it_bounds_the_prompt_length(): void
    {
        // The cap is the demo's only defence against someone using a shared
        // free quota as free inference.
        $this->postJson('/demo', ['prompt' => str_repeat('a', 601)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('prompt');
    }
}
