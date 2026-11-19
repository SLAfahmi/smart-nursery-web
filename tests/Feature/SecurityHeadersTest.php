<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers(): void
    {
        $responses = [
            $this->get('/login'),
            $this->actingAs(User::factory()->create())->get('/dashboard'),
        ];

        foreach ($responses as $response) {
            $response->assertOk()
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'same-origin')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_device_api_also_sends_headers(): void
    {
        $this->postJson('/api/v1/telemetry', [])
            ->assertStatus(401)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
