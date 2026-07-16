<?php

namespace Tests\Feature;

use App\Http\Controllers\Lab\ChatController;
use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

class PrismLabChatTest extends TestCase
{
    public function test_it_keeps_prism_lab_routes_out_of_non_local_environments(): void
    {
        $this->expectException(RouteNotFoundException::class);

        route('lab.chat', absolute: false);
    }

    public function test_it_returns_an_actionable_response_when_a_provider_key_is_missing(): void
    {
        config()->set('prism.providers.openai.api_key', '');

        $request = Request::create('/lab/chat', 'POST', [
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'feature' => 'text',
            'prompt' => 'Hello',
        ]);

        $response = app(ChatController::class)->run($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Add OPENAI_API_KEY to repos/prism-sandbox/.env, then reload the app.',
            'missing_key' => true,
        ], $response->getData(true));
    }
}
