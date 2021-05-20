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


//  echo 'test';
        // exit;

        $categories_json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/categories");
        $data = json_decode($categories_json);

        //   print_r($categories);

        foreach ($data->categories as $category) {


            $category_json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/categories/" . $category->id);
            $category_data = json_decode($category_json);




            $category = $category_data->category;

            $category_data = array(
                'title' => $category->title,
                'slug' => Str::slug($category->title),
                'standfirst' => $category->feature,
                'description' => strip_tags($category->description),
                'status' => 'draft',
                'created_by' => 1
            );

            $new_category = Category::create($category_data);


            // echo "<pre>";
            // print_r($category_data);
            // echo "</pre>";



            foreach ($category->products as $product) {
                $product_json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/products/" . $product->id);
                $product_data = json_decode($product_json);

                $product = $product_data->product;

                // echo "<pre>";
                // print_r($product);
                // echo "</pre>";

                $product_data = array(
                    'title' => $product->title,
                    'slug' => Str::slug($product->title),
                    'standfirst' => $product->feature,
                    'description' => strip_tags($product->description),
                    'status' => 'draft',
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

                   // $fi_header = $this->get_http_response_code($product->feature_image);
                   // if ($fi_header === 200) {
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
                  //  }
                }

                // product images 
                if (count($product->images) > 0) {
                    foreach ($product->images as $image) {

                        $image_header = $this->get_http_response_code($image->url);
                        if ($image_header === 200) {
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
                }

                $product_variant = array(
                    'product_id' => $new_product->id,
                    'sku' => strtoupper(uniqid()),
                    'price' => 0,
                    'weight' => 1,
                    'length' => 1,
                    'width' => 1,
                    'height' => 1,
                    'stock' => 1,
                    'created_by' => 1
                );

                ProductVariant::create($product_variant);




                // echo "<pre>";
                // print_r($product_data);
                // print_r($product_category_data);
                // print_r($product_image_data);
                // echo "</pre>";
            }

            //  exit;
        }







        // $json = file_get_contents("https://www.trinityhouse.co.uk/api/shop/products");
        // $data = json_decode($json);
        // $fake = Faker\Factory::create();

        // $categories = Category::pluck('id');

        // foreach($data->products as $product_item) {
        //     $product = array(
        //     'title' => $product_item->title,
        //     'slug' => $fake->slug(),
        //     'standfirst' => $fake->text(100),
        //     'description' => $fake->text(200),
        //     'status' => 'published',
        //     'created_by' => 1
        //     );

        //     $product = Product::create($product);

        //     // product categories
        //     for($pi = 0; $pi < rand(1,3); $pi++) {
        //         $product_category = array(
        //             'product_id' => $product->id,
        //             'category_id' => $categories[rand(0, count($categories)-1)],
        //         );

        //         ProductCategory::create($product_category);
        //     }

        //     // product images 
        //     for($pi = 0; $pi < rand(1,3); $pi++) {
        //         $product_image = array(
        //             'product_id' => $product->id,
        //             'description' => $fake->text(20),
        //             'location' => $fake->imageUrl(640, 480),
        //         );

        //         ProductImage::create($product_image);
        //     }

        //     // product variant types 
        //     $types = array(
        //         array(
        //             'name' => 'Size',
        //             'options' => 'Small, Medium, Large',
        //         ),
        //         array(
        //             'name' => 'Colour',
        //             'options' => 'Red, Green, Blue',
        //         ),
        //         array(
        //             'name' => 'Framing',
        //             'options' => 'Mounted, Unmounted',
        //         ),
        //         array(
        //             'name' => 'Pack Quantity',
        //             'options' => '4,8,12',
        //         ),
        //         array(
        //             'name' => 'Edition',
        //             'options' => 'New, Old',
        //         ),
        //     );

        //     for($vt = 0; $vt < rand(1,3); $vt++) {

        //         $option = $types[rand(0,4)];
        //         $product_variant_type = array(
        //             'product_id' => $product->id,
        //             'variant_no' => $vt+1,
        //             'name' => $option['name'],
        //             'options' => $option['options'],
        //             'created_by' => 1
        //         );

        //         ProductVariantType::create($product_variant_type);

        //         $no_of_variant_types = DB::table('product_variant_types')
        //         ->where('product_id', $product->id)
        //         ->where('name', $option['name'])
        //         ->count();

        //         if($no_of_variant_types > 1) {
        //             $variant_type = DB::table('product_variant_types')->where('product_id', $product->id)
        //                 ->where('name', $option['name'])
        //                 ->first();

        //             DB::table('product_variant_types')
        //             ->where('product_id', $product->id)
        //             ->where('name', $option['name'])
        //             ->where('id', '!=', $variant_type->id)
        //             ->delete();
        //         }
        //     }

        //     // product variants 
        //     $product_variant_types = ProductVariantType::where('product_id', $product->id)->get(['id', 'options'])->toArray();
        //     for($pv = 0; $pv < rand(1,5); $pv++) {
        //         $product_variant = array(
        //             'product_id' => $product->id,
        //             'sku' => strtoupper(uniqid()),
        //             'price' => rand(9, 99),
        //             'weight' => rand(5,50),
        //             'length' => rand(9, 30),
        //             'width' => rand(9, 20),
        //             'height' => rand(0.5, 16),
        //             'stock' => rand(9, 99),
        //             'variant_1_id' => isset($product_variant_types[0])? $product_variant_types[0]['id'] : null,
        //             'variant_1_value' => isset($product_variant_types[0])? trim(explode(',', $product_variant_types[0]['options'])[rand(0, count(explode(',', $product_variant_types[0]['options']))-1)]) : null,
        //             'variant_2_id' => isset($product_variant_types[1])? $product_variant_types[1]['id'] : null,
        //             'variant_2_value' => isset($product_variant_types[1])? trim(explode(',', $product_variant_types[1]['options'])[rand(0, count(explode(',', $product_variant_types[1]['options']))-1)]) : null,
        //             'variant_3_id' => isset($product_variant_types[2])? $product_variant_types[2]['id'] : null,
        //             'variant_3_value' => isset($product_variant_types[2])? trim(explode(',', $product_variant_types[2]['options'])[rand(0, count(explode(',', $product_variant_types[2]['options']))-1)]) : null,
        //             'created_by' => 1
        //         );

        //         ProductVariant::create($product_variant);
        //    }
      //  }
    }


function get_http_response_code($url)
{
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}

}
