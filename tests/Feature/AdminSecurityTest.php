<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function clearAuth(): void
    {
        auth()->forgetGuards();
        app('tymon.jwt')->unsetToken();
    }

    private function probe(string $token, string $path = '/api/auth/me')
    {
        $this->clearAuth();
        return $this->getJson($path, ['Authorization' => 'Bearer '.$token]);
    }

    public function test_only_active_administrators_can_sign_in(): void
    {
        foreach ([['admin' => 0], ['admin' => 1, 'disabled' => 1], ['admin' => 1, 'deleted_at' => now()]] as $attributes) {
            $user = User::factory()->create($attributes);
            $this->clearAuth();
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
                ->assertUnauthorized()->assertJsonMissingPath('access_token');
        }
    }

    public function test_all_private_routes_enforce_administrator_permissions_before_the_controller(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $customer = User::factory()->create(['admin' => 0]);
        $token = auth('api')->login($customer);
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array('auth:api', $route->gatherMiddleware(), true)) continue;
            $this->assertContains('admin', $route->gatherMiddleware(), $route->uri());
            $path = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->clearAuth();
            $this->json($route->methods()[0], $path, [], ['Authorization' => 'Bearer '.$token])->assertForbidden();
            $count++;
        }
        $this->assertGreaterThan(90, $count);
    }

    public function test_disabled_deleted_and_demoted_users_lose_existing_access(): void
    {
        foreach (['disabled' => 1, 'deleted_at' => now(), 'admin' => 0] as $field => $value) {
            $user = User::factory()->create(['admin' => 1]);
            $token = auth('api')->login($user);
            $this->probe($token)->assertOk();
            DB::table('users')->where('id', $user->id)->update([$field => $value]);
            $this->probe($token)->assertStatus($field === 'admin' ? 403 : 401);
        }
    }

    public function test_password_reset_invalidates_existing_access_and_refresh_tokens(): void
    {
        $user = User::factory()->create(['admin' => 1]);
        $old = auth('api')->login($user);
        app(PasswordReset::class)->request($user->email);
        $resetToken = Crypt::decryptString($user->fresh()->password_reset_payload);
        $this->assertTrue(app(PasswordReset::class)->reset($user->email, $resetToken, 'a long reset passphrase'));
        $this->probe($old)->assertUnauthorized();
        $this->clearAuth();
        $this->postJson('/api/auth/refresh', [], ['Authorization' => 'Bearer '.$old])->assertUnauthorized();
        $this->clearAuth();
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'a long reset passphrase'])->assertOk();
        $this->probe($login->json('access_token'))->assertOk();
    }

    public function test_account_changes_revoke_tokens_even_if_the_account_is_reenabled(): void
    {
        $operator = User::factory()->create(['admin' => 1]);
        $user = User::factory()->create(['admin' => 1]);
        $old = auth('api')->login($user);
        $this->clearAuth();
        $this->actingAs($operator, 'api');
        $this->putJson('/api/auth/users/'.$user->id, ['disabled' => 1])->assertOk()->assertJsonPath('status', 'success');
        $this->putJson('/api/auth/users/'.$user->id, ['disabled' => 0])->assertOk();
        $this->probe($old)->assertUnauthorized();
        $this->clearAuth();
        $this->getJson('/api/auth/me?token='.$old)->assertUnauthorized();
    }

    public function test_login_has_per_account_and_per_ip_limits(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2']);
        $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
    }
}
