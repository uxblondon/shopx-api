<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    private function issue(User $user): string
    {
        app(PasswordReset::class)->request($user->email);
        return Crypt::decryptString($user->fresh()->password_reset_payload);
    }

    private function resetLink(User $user, string $token, array $overrides = [])
    {
        return $this->postJson('/api/auth/reset/'.$token.'/password', array_merge([
            'token' => $token, 'email' => $user->email,
            'password' => 'a long new passphrase', 'password_confirmation' => 'a long new passphrase',
        ], $overrides));
    }

    public function test_request_does_not_disclose_accounts_and_stores_only_hash_and_encrypted_pending_payload(): void
    {
        $user = User::factory()->create();
        $known = $this->postJson('/api/auth/send-password-reset-link', ['email' => $user->email])->assertOk();
        $missing = $this->postJson('/api/auth/send-password-reset-link', ['email' => 'missing@example.test'])->assertOk();
        $this->assertSame($known->json(), $missing->json());
        $user->refresh(); $token = Crypt::decryptString($user->password_reset_payload);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame(hash('sha256', $token), $user->password_reset_token);
        $this->assertStringNotContainsString($token, $user->password_reset_payload);
        foreach (['password_reset_token', 'password_reset_payload', 'password_reset_expires_at', 'password_reset_token_created_at'] as $key) {
            $this->assertArrayNotHasKey($key, $user->toArray());
        }
        $this->assertEqualsWithDelta(3600, now()->diffInSeconds($user->password_reset_expires_at), 2);
    }

    public function test_reset_survives_email_delivery_and_can_be_used_only_once(): void
    {
        $user = User::factory()->create(); $token = $this->issue($user);
        $this->artisan('send:emails')->assertExitCode(0);
        $this->assertNull($user->fresh()->password_reset_payload);
        $message = Mail::mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();
        $this->assertStringContainsString('/reset/'.$token.'/password', $message->getHtmlBody());
        $this->resetLink($user, $token)->assertOk()->assertJsonPath('status', 'success');
        $this->assertTrue(Hash::check('a long new passphrase', $user->fresh()->password));
        $this->assertNull($user->fresh()->password_reset_token);
        $this->resetLink($user, $token)->assertUnprocessable();
    }

    public function test_expired_links_are_rejected_even_after_email_was_delivered(): void
    {
        $user = User::factory()->create(); $token = $this->issue($user);
        $this->artisan('send:emails')->assertExitCode(0);
        $this->travel(60)->minutes();
        $this->resetLink($user, $token)->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_expired_pending_mail_is_discarded_without_sending(): void
    {
        $user = User::factory()->create(); $this->issue($user);
        $this->travel(61)->minutes(); Mail::fake();
        $this->artisan('send:emails')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertNull($user->fresh()->password_reset_payload);
        $this->assertNull($user->fresh()->password_reset_token);
    }

    public function test_one_minute_cooldown_and_replacement_invalidate_only_the_old_link(): void
    {
        $user = User::factory()->create(); $old = $this->issue($user);
        $this->assertSame($old, $this->issue($user));
        $this->travel(61)->seconds(); $new = $this->issue($user);
        $this->assertNotSame($old, $new);
        $this->resetLink($user, $old)->assertUnprocessable();
        $this->resetLink($user, $new)->assertOk();
    }

    public function test_wrong_email_wrong_token_and_contradictory_route_do_not_change_password(): void
    {
        $user = User::factory()->create(); $token = $this->issue($user);
        $this->resetLink($user, $token, ['email' => 'missing@example.test'])->assertUnprocessable();
        $this->resetLink($user, str_repeat('a', 64))->assertUnprocessable();
        $this->resetLink($user, $token, ['token' => str_repeat('b', 64)])->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_disabled_and_deleted_accounts_cannot_request_or_use_reset_links(): void
    {
        foreach (['disabled' => 1, 'deleted_at' => now()] as $field => $value) {
            $user = User::factory()->create(); $token = $this->issue($user);
            $user->forceFill([$field => $value])->save();
            $this->resetLink($user, $token)->assertUnprocessable();
            $this->travel(61)->seconds();
            app(PasswordReset::class)->request($user->email);
            $this->assertSame(hash('sha256', $token), $user->fresh()->password_reset_token);
        }
    }

    public function test_strong_confirmed_password_is_required_without_consuming_link_on_validation_failure(): void
    {
        $user = User::factory()->create(); $token = $this->issue($user);
        foreach (['short', str_repeat('a', 73), str_repeat('é', 37), 'fifteen chars'."\0".'here'] as $password) {
            $this->resetLink($user, $token, ['password' => $password, 'password_confirmation' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->resetLink($user, $token, ['password_confirmation' => 'different'])->assertUnprocessable();
        $this->resetLink($user, $token)->assertOk();
    }

    public function test_legacy_plaintext_link_has_no_validity_and_existing_login_still_works(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password_reset_token' => str_repeat('a', 64), 'password_reset_token_created_at' => now()])->save();
        $this->resetLink($user, str_repeat('a', 64))->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_user_management_enforces_same_policy_and_invalidates_outstanding_links(): void
    {
        $admin = User::factory()->create(['admin' => 1]);
        $this->actingAs($admin, 'api');
        $this->postJson('/api/auth/users', ['name' => 'New admin', 'email' => 'new@example.test',
            'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable();
        $this->postJson('/api/auth/users', ['name' => 'New admin', 'email' => 'new@example.test',
            'password' => 'a long new passphrase', 'password_confirmation' => 'a long new passphrase'])
            ->assertOk()->assertJsonPath('status', 'success');
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $token = $this->issue($user);
        $this->putJson('/api/auth/users/'.$user->id, ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable();
        $this->putJson('/api/auth/users/'.$user->id, ['password' => 'another long passphrase', 'password_confirmation' => 'another long passphrase'])
            ->assertOk()->assertJsonPath('status', 'success');
        $this->assertNull($user->fresh()->password_reset_token);
        $this->assertTrue(Hash::check('another long passphrase', $user->fresh()->password));
        $this->resetLink($user, $token)->assertUnprocessable();
    }

    public function test_requests_and_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/send-password-reset-link', ['email' => 'missing@example.test'])->assertOk();
        }
        $this->postJson('/api/auth/send-password-reset-link', ['email' => 'missing@example.test'])->assertStatus(429);
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->resetLink($user, str_repeat('a', 64))->assertUnprocessable();
        }
        $this->resetLink($user, str_repeat('a', 64))->assertStatus(429);
    }
}
