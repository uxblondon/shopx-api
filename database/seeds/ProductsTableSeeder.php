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
        // make product id started from 1
        DB::statement('ALTER TABLE products AUTO_INCREMENT = 1');

        $categories_json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/categories");
        $data = json_decode($categories_json);

        foreach ($data->categories as $category) {


            $category_json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/categories/" . $category->id);
            $category_data = json_decode($category_json);

            $category = $category_data->category;

            $category_data = array(
                'title' => $category->title,
                'slug' => Str::slug($category->title),
                'standfirst' => $category->feature,
                'description' => strip_tags($category->description),
                'status' => 'published',
                'created_by' => 1
            );

            $new_category = Category::create($category_data);

            foreach ($category->products as $product) {
                $product_json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/products/" . $product->id);
                $product_data = json_decode($product_json);

                $product = $product_data->product;

                $product_data = array(
                    'title' => $product->title,
                    'slug' => Str::slug($product->title),
                    'standfirst' => $product->feature,
                    'description' => strip_tags($product->description),
                    'status' => 'published',
                    'created_by' => 1
                );

                $new_product = Product::create($product_data);

                $product_category_data = array(
                    'product_id' => $new_product->id,
                    'category_id' => $new_category->id,
                );

                ProductCategory::create($product_category_data);

                // product feature image 
                if (trim($product->feature_image) != '') {
                        $fi_content = file_get_contents($product->feature_image);
                        if ($fi_content) {
                            $fi_location = str_replace('', '', 'products/' . $product->id . '/' . $product->feature_image_name);
                            Storage::disk('s3')->put($fi_location, $fi_content);

                            $product_fi_data = array(
                                'product_id' => $new_product->id,
                                'description' => $product->feature_image_description,
                                'location' => Storage::url($fi_location),
                                'feature_image' => 1,
                            );
                            ProductImage::create($product_fi_data);
                        }
                }

                // product images 
                if (count($product->images) > 0) {
                    foreach ($product->images as $image) {
                            $image_content = file_get_contents($image->url);
                            if ($image_content) {
                                $image_location = str_replace('', '', 'products/' . $new_product->id . '/' . $image->name);
                                Storage::disk('s3')->put($image_location, $image_content);

                                $product_image_data = array(
                                    'product_id' => $new_product->id,
                                    'description' => $image->description,
                                    'location' => Storage::url($image_location),
                                );

                                ProductImage::create($product_image_data);
                            }
                    }
                }

                $product_variant = array(
                    'product_id' => $new_product->id,
                    'sku' => strtoupper(uniqid()),
                    'price' => 1,
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
