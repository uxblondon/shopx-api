<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->string('payment_type', 60)->nullable();
            $table->decimal('amount', 8, 2);
            $table->string('payment_id', 60)->nullable();
            $table->string('payment_status', 25)->nullable();
            $table->boolean('payment_confirmed')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreign('order_id')
            ->references('id')
            ->on('orders')
            ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_payments');
    }
}
