<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShippingRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->bigInteger('shipping_zone_id')->unsigned();
            $table->bigInteger('package_size_id')->unsigned();
            $table->bigInteger('shipping_option_id')->unsigned();
            $table->string('cost_based_on', 32);
            $table->decimal('min_value', 8, 2)->default(0.0);
            $table->decimal('max_value', 8, 2)->default(0.0);
            $table->decimal('min_weight', 8, 2)->default(0.0);
            $table->decimal('max_weight', 8, 2)->default(0.0);
            $table->decimal('cost', 8, 2);
          //  $table->boolean('cover_damage')->default(0);
         //   $table->decimal('max_cover_amount', 8, 2)->default(0.0);
            $table->boolean('available')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
        });

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->foreign('shipping_zone_id')
            ->references('id')
            ->on('shipping_zones')
            ->onDelete('cascade');
        });

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->foreign('package_size_id')
            ->references('id')
            ->on('shipping_package_sizes')
            ->onDelete('cascade');
        });

        Schema::table('shipping_rates', function (Blueprint $table) {
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
        Schema::dropIfExists('shipping_rates');
    }
}
