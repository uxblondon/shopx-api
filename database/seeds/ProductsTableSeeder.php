<?php

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariantType;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Category;

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

        $categories = Category::pluck('id');

        for ($i=0; $i<10; $i++) {
            $product = array(
            'title' => $fake->text(20),
            'slug' => $fake->slug(),
            'standfirst' => $fake->text(100),
            'description' => $fake->text(200),
            'created_by' => 1
            );

            $product = Product::create($product);

            // product categories
            for($pi = 0; $pi < rand(1,3); $pi++) {
                $product_category = array(
                    'product_id' => $product->id,
                    'category_id' => $categories[rand(0, count($categories)-1)],
                );

                ProductCategory::create($product_category);
            }

            // product images 
            for($pi = 0; $pi < rand(1,3); $pi++) {
                $product_image = array(
                    'product_id' => $product->id,
                    'description' => $fake->text(20),
                    'location' => $fake->imageUrl(640, 480),
                );

                ProductImage::create($product_image);
            }

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
                    'variant_no' => $vt+1,
                    'name' => $option['name'],
                    'options' => $option['options'],
                    'created_by' => 1
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

            // product variants 
            $product_variant_types = ProductVariantType::where('product_id', $product->id)->get(['id', 'options'])->toArray();
            for($pv = 0; $pv < rand(1,5); $pv++) {
                $product_variant = array(
                    'product_id' => $product->id,
                    'sku' => $fake->ean8,
                    'price' => rand(9, 99),
                    'weight' => rand(5,50)/100,
                    'variant_1_id' => isset($product_variant_types[0])? $product_variant_types[0]['id'] : null,
                    'variant_1_value' => isset($product_variant_types[0])? trim(explode(',', $product_variant_types[0]['options'])[rand(0, count(explode(',', $product_variant_types[0]['options']))-1)]) : null,
                    'variant_2_id' => isset($product_variant_types[1])? $product_variant_types[1]['id'] : null,
                    'variant_2_value' => isset($product_variant_types[1])? trim(explode(',', $product_variant_types[1]['options'])[rand(0, count(explode(',', $product_variant_types[1]['options']))-1)]) : null,
                    'variant_3_id' => isset($product_variant_types[2])? $product_variant_types[2]['id'] : null,
                    'variant_3_value' => isset($product_variant_types[2])? trim(explode(',', $product_variant_types[2]['options'])[rand(0, count(explode(',', $product_variant_types[2]['options']))-1)]) : null,
                    'created_by' => 1
                );

                ProductVariant::create($product_variant);
            }
        }
    }
}
