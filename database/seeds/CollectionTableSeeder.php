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
                'active' => 1,
                'created_by' => 1,
            );
            $collection_point = CollectionPoint::create($collection_point_data);

            $collection_rate1 = array(
                'collection_point_id' => $collection_point->id,
                'speed' => '3-5 working day',
                'cost' => 0,
                'created_by' => 1,
            );

            CollectionRate::create($collection_rate1);

            $collection_rate2 = array(
                'collection_point_id' => $collection_point->id,
                'speed' => 'Next working day',
                'cost' => 5,
                'created_by' => 1,
            );
            CollectionRate::create($collection_rate2);
        }
    }
}
