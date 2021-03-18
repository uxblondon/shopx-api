<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShippingCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shipping_countries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->bigInteger('shipping_zone_id')->unsigned();
            $table->string('country_code');
            $table->dateTime('created_at');
            $table->integer('created_by');
        });

        Schema::table('shipping_countries', function (Blueprint $table) {
            $table->foreign('shipping_zone_id')
            ->references('id')
            ->on('shipping_zones')
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
        Schema::dropIfExists('shipping_countries');
    }
}
