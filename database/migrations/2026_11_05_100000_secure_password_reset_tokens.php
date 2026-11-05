<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('password_reset_payload')->nullable();
            $table->dateTime('password_reset_expires_at')->nullable();
        });
        // Legacy links have no reliable expiry (the sender cleared their timestamp).
        // Invalidate links only; existing password hashes and account data are untouched.
        DB::table('users')->update(['password_reset_token' => null, 'password_reset_token_created_at' => null]);
    }

    public function down(): void
    {
        DB::table('users')->update(['password_reset_token' => null, 'password_reset_token_created_at' => null]);
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['password_reset_payload', 'password_reset_expires_at']));
    }
};
