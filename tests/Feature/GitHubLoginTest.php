<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GitHubLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_links_and_authenticates_the_user(): void
    {
        $githubUser = Mockery::mock(SocialiteUser::class);
        $githubUser->shouldReceive('getId')->andReturn('98765');
        $githubUser->shouldReceive('getNickname')->andReturn('octocat');
        $githubUser->shouldReceive('getName')->andReturn('Octo Cat');
        $githubUser->shouldReceive('getEmail')->andReturn('octo@example.com');
        $githubUser->shouldReceive('getAvatar')->andReturn('https://avatars/octocat');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($githubUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $this->get('/auth/github/callback')->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'github_id' => '98765',
            'github_username' => 'octocat',
            'avatar_url' => 'https://avatars/octocat',
        ]);
    }
}
