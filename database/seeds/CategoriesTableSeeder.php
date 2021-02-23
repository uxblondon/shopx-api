<?php

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fake = Faker\Factory::create();

        $categories = array(
            'Cards and calendars',
            'Lighthouse models',
            'Homeware',
            'Vessel merchandise',
            'Books, DVDs and games',
            'Historic lighthouse prints',
            'Trinity House Clothing',
            'Quincentenary gifts',
        );

        foreach ($categories as $category) {
            $category_data = array(
                'title' => $category,
                'slug' => $fake->slug(),
                'feature_image' => $fake->imageUrl(640, 480),
                'standfirst' => $fake->text(100),
                'description' => $fake->text(200),
                'created_by' => 1
            );

            Category::create($category_data);
        }
    }
}
