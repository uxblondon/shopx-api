<?php

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariantType;

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

            $product = Product::create($product);

            // product variant types 
            $types = array(
                array(
                    'name' => 'Size',
                    'options' => 'Small, Medium, Large',
                ),
                array(
                    'name' => 'Colour',
                    'options' => 'Red, Green, Blue',
                ),
                array(
                    'name' => 'Framing',
                    'options' => 'Mounted, Unmounted',
                ),
                array(
                    'name' => 'Pack Quantity',
                    'options' => '4,8,12',
                ),
                array(
                    'name' => 'Edition',
                    'options' => 'New, Old',
                ),
            );

            for($vt = 0; $vt < rand(1,3); $vt++) {

                $option = $types[rand(0,4)];
                $product_variant_type = array(
                    'product_id' => $product->id,
                    'name' => $option['name'],
                    'options' => $option['options']
                );

                ProductVariantType::create($product_variant_type);

                $no_of_variant_types = DB::table('product_variant_types')
                ->where('product_id', $product->id)
                ->where('name', $option['name'])
                ->count();

                if($no_of_variant_types > 1) {
                    $variant_type = DB::table('product_variant_types')->where('product_id', $product->id)
                        ->where('name', $option['name'])
                        ->first();

                    DB::table('product_variant_types')
                    ->where('product_id', $product->id)
                    ->where('name', $option['name'])
                    ->where('id', '!=', $variant_type->id)
                    ->delete();
                }
            }
        }
    }
}
