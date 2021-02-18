<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('categroy_id')->nullable();
            
            $table->string('slug');
            $table->string('title');
            $table->string('standfirst')->nullable();
            $table->string('feature_image')->nullable();
            $table->longText('description');
            $table->integer('sort_order');
            $table->string('status', 20);

            $table->string('meta_description');
            $table->string('meta_keywords');

            $table->timestamps();
            $table->softDeletes();
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
