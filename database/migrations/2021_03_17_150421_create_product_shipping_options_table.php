<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductShippingOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_shipping_options', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('shipping_option_id')->unsigned();
            $table->timestamps();
            $table->softDeletes();
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
        });

        Schema::table('product_shipping_options', function (Blueprint $table) {
            $table->foreign('product_id')
            ->references('id')
            ->on('products')
            ->onDelete('cascade');
        });

        Schema::table('product_shipping_options', function (Blueprint $table) {
            $table->foreign('shipping_option_id')
            ->references('id')
            ->on('shipping_options')
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
        Schema::dropIfExists('product_shipping_options');
    }
}
