<?php

use Illuminate\Database\Seeder;
use App\Models\CollectionPoint;
use App\Models\CollectionRate;

class CollectionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fake = Faker\Factory::create();

        for ($a = 0; $a < 3; $a++) {
            $collection_point_data = array(
                'name' => $fake->name,
                'address_line_1' => $fake->streetName,
                'city' => $fake->city,
                'postcode' => $fake->postcode,
                'country_code' => 'GB',
                'created_by' => 1,
            );
            CollectionPoint::create($collection_point_data);
        }
    }
}
