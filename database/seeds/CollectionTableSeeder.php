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
        $collection_points = array(
            [
                'name' => 'Trinity House Harwich',
                'address_line_1' => 'Harwich Quay',
                'city' => 'Harwich',
                'postcode' => 'CO12 3JW',
                'county' => 'Essex',
                'country_code' => 'GB',
                'active' => 1,
                'created_by' => 1,
            ],
            [
                'name' => 'Trinity House London',
                'address_line_1' => 'Tower Hill',
                'city' => 'London',
                'postcode' => 'EC3N 4DH',
                'county' => '',
                'country_code' => 'GB',
                'active' => 1,
                'created_by' => 1,
            ],
        );

        foreach ($collection_points as $collection_point_data) {
            
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
