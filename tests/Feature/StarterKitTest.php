<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_login_and_register_screens_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_users_can_register_and_land_on_the_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.test']);
    }

    public function test_dashboard_and_settings_require_auth(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/settings/profile')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/profile')->assertOk();
        $this->actingAs($user)->get('/settings/password')->assertOk();
    }
}
