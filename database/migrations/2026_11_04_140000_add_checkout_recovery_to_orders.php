<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('state', 16)->default('draft');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_token_hash', 64)->nullable()->unique();
            $table->string('checkout_payload_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['checkout_token_hash']);
            $table->dropColumn(['checkout_token_hash', 'checkout_payload_hash']);
        });
    }
};
