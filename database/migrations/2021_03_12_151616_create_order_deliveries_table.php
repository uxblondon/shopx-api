<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('shipping_rate_id')->unsigned();
            $table->text('shipping_label');
            $table->boolean('different_billing_address')->default(0);
            $table->decimal('cost', 8, 2);
            $table->decimal('additional_cost', 8, 2)->default(0.0);
            $table->boolean('cover_damage')->default(0);
            $table->decimal('max_cover_amount', 8, 2)->default(0.0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_deliveries');
    }
}
