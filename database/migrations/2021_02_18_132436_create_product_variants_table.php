<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductVariantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->bigInteger('product_id')->unsigned();
            $table->string('sku')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('weight', 8, 2)->default(0.0);

            $table->decimal('length', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            
            
            $table->boolean('shipping_not_required')->default(0);
            $table->boolean('separated_shipping_required')->default(0);
            $table->decimal('additional_shipping_cost', 8, 2)->default(0.0);

            $table->bigInteger('variant_1_id')->nullable();
            $table->string('variant_1_value')->nullable();
            $table->bigInteger('variant_2_id')->nullable();
            $table->string('variant_2_value')->nullable();
            $table->bigInteger('variant_3_id')->nullable();
            $table->string('variant_3_value')->nullable();

            $table->boolean('available')->default(0);

            $table->timestamps();
            $table->softDeletes();
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreign('product_id')
            ->references('id')
            ->on('products')
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
        Schema::dropIfExists('product_variants');
    }
}
