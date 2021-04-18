<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('variant_id')->unsigned();
            $table->string('title');
          
            $table->decimal('width', 8, 2);
            $table->decimal('length', 8, 2);
            $table->decimal('height', 8, 2);
            $table->decimal('weight', 8, 2);
            
            $table->boolean('shipping_not_required')->default(0);
            $table->boolean('separated_shipping_required');
            $table->decimal('additional_shipping_cost', 8, 2);

            $table->string('variant_1_name')->nullable();
            $table->string('variant_1_value')->nullable();
            $table->string('variant_2_name')->nullable();
            $table->string('variant_2_value')->nullable();
            $table->string('variant_3_name')->nullable();
            $table->string('variant_3_value')->nullable();
            $table->decimal('price', 8, 2);
            $table->integer('quantity');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('order_items', function (Blueprint $table) {
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
        Schema::dropIfExists('order_items');
    }
}
