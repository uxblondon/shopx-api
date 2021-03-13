<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShippingPackageSizesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shipping_package_sizes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('format');
            
            $table->integer('length');
            $table->integer('width');
            $table->integer('height');

            $table->integer('min_weight');
            $table->integer('max_weight');

            $table->boolean('available')->default(1);
            $table->text('remark')->nullable();
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
        Schema::dropIfExists('shipping_package_sizes');
    }
}
