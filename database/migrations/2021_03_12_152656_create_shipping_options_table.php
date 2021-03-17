<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShippingOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shipping_options', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider');
            $table->string('service');
            $table->text('speed')->nullable();
            $table->boolean('has_tracking')->default(0);
            $table->string('tracking_type')->nullable();
            $table->decimal('min_weight', 8, 2)->default(0.0);
            $table->decimal('max_weight', 8, 2)->default(0.0);        
            $table->boolean('is_collection')->default(0);
            $table->boolean('available')->default(1);
            $table->text('note')->nullable();
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
        Schema::dropIfExists('shipping_options');
    }
}
