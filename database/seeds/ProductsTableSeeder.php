<?php

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fake = Faker\Factory::create();

        // make product id started from 1
        DB::statement('ALTER TABLE products AUTO_INCREMENT = 1');

        for ($i=0; $i<10; $i++) {
            $product = array(
            'categroy_id' => $fake->randomDigit,
            'title' => $fake->text(20),
            'slug' => $fake->slug(),
            'standfirst' => $fake->text(100),
            'description' => $fake->text(200),
            'created_by' => 1
            );

            Product::create($product);
        }
    }
}
