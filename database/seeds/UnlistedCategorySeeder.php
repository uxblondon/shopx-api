<?php

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Category;

class UnlistedCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // make product id started from 1
        DB::statement('ALTER TABLE products AUTO_INCREMENT = 1');

        $unlisted_category_data = array(
            'title' => 'Lighthouse cottage payments',
            'slug' => Str::slug('Lighthouse cottage payments'),
            'standfirst' => 'Payment pages for internal lighthouse cottage bookings',
            'description' => strip_tags(''),
            'status' => 'unlisted',
            'created_by' => 1
        );

        $unlisted_category = Category::create($unlisted_category_data);

        $category_json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/lcp");
        $products = json_decode($category_json, true);


        foreach ($products as $product_list) {

            if (is_array($product_list) && count($product_list) > 0) {
                foreach ($product_list as $product_array) {

                    $product = (object)$product_array;

                    $product_data = array(
                        'title' => $product->title,
                        'slug' => Str::slug($product->title),
                        'standfirst' => $product->feature,
                        'description' => '',
                        'status' => 'published',
                        'created_by' => 1
                    );

                    $new_product = Product::create($product_data);

                    $product_category_data = array(
                        'product_id' => $new_product->id,
                        'category_id' => $unlisted_category->id,
                    );

                    ProductCategory::create($product_category_data);

                    // product feature image 
                    if (trim($product->image) != '') {
                        $fi_content = file_get_contents($product->image);
                        if ($fi_content) {
                            $fi_location = str_replace('', '', 'products/' . $new_product->id . '/' . $product->image_name);
                            Storage::disk('s3')->put($fi_location, $fi_content);

                            $product_fi_data = array(
                                'product_id' => $new_product->id,
                                'description' => $product->title,
                                'location' => Storage::url($fi_location),
                                'feature_image' => 1,
                            );
                            ProductImage::create($product_fi_data);
                        }
                    }

                    $product_variant = array(
                        'product_id' => $new_product->id,
                        'sku' => strtoupper(uniqid()),
                        'price' => $product->price,
                        'weight' => 1,
                        'length' => 1,
                        'width' => 1,
                        'height' => 1,
                        'stock' => 1,
                        'created_by' => 1
                    );

                    ProductVariant::create($product_variant);
                }
            }
        }
    }
}
