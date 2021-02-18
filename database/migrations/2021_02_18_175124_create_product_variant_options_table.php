<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductVariantOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigIncrements('product_variant_id');

            $table->bigIncrements('variant_1_id')->nullable();
            $table->string('variant_1_value')->nullable();

            $table->bigIncrements('variant_2_id')->nullable();
            $table->string('variant_2_value')->nullable();

            $table->bigIncrements('variant_3_id')->nullable();
            $table->string('variant_3_value')->nullable();

            $table->timestamps();
            $table->foreign('product_variant_id')
            ->references('id')
            ->on('product_variants')
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
        Schema::dropIfExists('product_variant_options');
    }
}
