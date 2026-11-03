<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('paypal_order_id', 64)->nullable()->unique();
            $table->uuid('paypal_request_id')->nullable()->unique();
        });
    }
    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropUnique(['paypal_order_id']);
            $table->dropUnique(['paypal_request_id']);
            $table->dropColumn(['paypal_order_id', 'paypal_request_id']);
        });
    }
};
