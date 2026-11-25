<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_successful_response(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_stylesheet_url_changes_with_file_version(): void
    {
        $version = filemtime(public_path('css/style.css'));

        $this->get('/login')->assertSee("css/style.css?v={$version}", false);
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertSee("css/style.css?v={$version}", false);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200)->assertSee('id="hero-clock"', false);
    }
}
