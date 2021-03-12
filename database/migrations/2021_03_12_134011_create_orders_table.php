<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->unsigned();
            
            $table->string('name', 100);
            $table->string('email', 150);

            $table->string('billing_address_line_1', 150);
            $table->string('billing_address_line_2', 150)->nullable();
            $table->string('billing_city', 100);
            $table->string('billing_county', 100)->nullable();
            $table->string('billing_postcode', 30);
            $table->string('billing_country', 50);

            $table->string('shipping_address_line_1', 150)->nullable();
            $table->string('shipping_address_line_2', 150);
            $table->string('shipping_address_city', 100);
            $table->string('shipping_address_county', 100);
            $table->string('shipping_address_postcode', 30);
            $table->string('shipping_address_country', 50);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->foreign('user_id')
            ->references('id')
            ->on('users')
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
        Schema::dropIfExists('orders');
    }
}
