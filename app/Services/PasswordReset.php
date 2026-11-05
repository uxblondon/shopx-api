<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordReset
{
    public const MINUTES = 60;

    public function request(string $email): void
    {
        // Generate/encrypt regardless of account existence; delivery happens asynchronously.
        $token = bin2hex(random_bytes(32));
        $payload = Crypt::encryptString($token);
        DB::transaction(function () use ($email, $token, $payload) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || $user->disabled || $user->deleted_at) {
                return;
            }
            // Repeated requests within one minute retain the same usable link.
            if ($user->password_reset_expires_at && $user->password_reset_expires_at->gt(now()->addMinutes(self::MINUTES)->subMinute())) {
                return;
            }
            $user->forceFill([
                'password_reset_token' => hash('sha256', $token),
                // Only the pending mail payload is reversible, protected by APP_KEY.
                'password_reset_payload' => $payload,
                'password_reset_token_created_at' => now(),
                'password_reset_expires_at' => now()->addMinutes(self::MINUTES),
            ])->save();
        });
    }

    public function reset(string $email, string $token, string $password): bool
    {
        return DB::transaction(function () use ($email, $token, $password) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || $user->disabled || $user->deleted_at || ! $user->password_reset_token
                || ! $user->password_reset_expires_at || $user->password_reset_expires_at->lte(now())
                || ! hash_equals($user->password_reset_token, hash('sha256', $token))) {
                return false;
            }
            $user->forceFill([
                'password' => Hash::make($password),
                'password_reset_token' => null,
                'password_reset_payload' => null,
                'password_reset_token_created_at' => null,
                'password_reset_expires_at' => null,
                'remember_token' => null,
            ])->save();
            return true;
        });
    }
}
