<?php

namespace Tests\Feature;

use Tests\TestCase;

class HitlDemoTest extends TestCase
{
    public function test_demo_page_renders(): void
    {
        $response = $this->get('/demo/hitl')->assertOk();

        $this->assertSame('DemoHitl', $response->viewData('page')['component']);
    }

    public function test_run_requires_api_key(): void
    {
        config()->set('prism.providers.anthropic.api_key', '');

        $this->postJson('/demo/hitl/run', ['run_id' => 'test-run-12345'])
            ->assertStatus(422);
    }

    public function test_resume_without_pending_approval_is_404(): void
    {
        $this->postJson('/demo/hitl/resume', ['run_id' => 'test-run-12345', 'approved' => true])
            ->assertNotFound();
    }
}
