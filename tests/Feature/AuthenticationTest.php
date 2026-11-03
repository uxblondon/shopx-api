<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_me_refresh_and_logout_preserve_jwt_contract(): void
    {
        $user = User::factory()->create(['admin' => 1]);
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in'])->assertJsonPath('token_type', 'bearer');
        $headers = ['Authorization' => 'Bearer '.$login->json('access_token')];
        auth()->forgetGuards();
        app('tymon.jwt')->unsetToken();
        $this->getJson('/api/auth/me', $headers)->assertOk()->assertJsonPath('email', $user->email)->assertJsonMissingPath('password');
        auth()->forgetGuards();
        app('tymon.jwt')->unsetToken();
        $refresh = $this->postJson('/api/auth/refresh', [], $headers)->assertOk()->assertJsonStructure(['access_token']);
        $headers = ['Authorization' => 'Bearer '.$refresh->json('access_token')];
        auth()->forgetGuards();
        app('tymon.jwt')->unsetToken();
        $this->postJson('/api/auth/logout', [], $headers)->assertOk()->assertJsonPath('message', 'Successfully logged out');
        // JWT singletons must be reset between simulated requests, as in PHP-FPM.
        auth()->forgetGuards();
        app('tymon.jwt')->unsetToken();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }

    public function test_invalid_login_does_not_return_a_token(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()->assertJsonMissingPath('access_token');
    }

    public function test_api_errors_use_real_http_statuses_without_leaking_debug_details(): void
    {
        $this->getJson('/api/does-not-exist')->assertNotFound()->assertJsonPath('status', 'error')->assertJsonMissingPath('trace');
        $this->get('/api/products', ['Accept' => 'text/html'])->assertUnauthorized();
    }

    public function test_password_reset_routes_are_loadable(): void
    {
        $this->postJson('/api/auth/send-password-reset-link', [])->assertUnprocessable();
        $this->postJson('/api/auth/reset/invalid/password', [])->assertUnprocessable();
    }
}
