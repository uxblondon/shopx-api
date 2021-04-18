<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDeliveryItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_delivery_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->bigInteger('order_delivery_id')->unsigned();
            $table->bigInteger('order_item_id')->unsigned();
            $table->timestamps();
        });

        Schema::table('order_delivery_items', function (Blueprint $table) {
            $table->foreign('order_item_id')
            ->references('id')
            ->on('order_items')
            ->onDelete('cascade');
        });

        Schema::table('order_delivery_items', function (Blueprint $table) {
            $table->foreign('order_delivery_id')
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
        Schema::dropIfExists('order_delivery_items');
    }
}
