<?php

use Illuminate\Database\Seeder;
use App\Models\StoreAddress;

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
            $address_data = array(
                'type' => 'collection',
                'address_line_1' => $fake->streetName,
                'city' => $fake->city,
                'postcode' => $fake->postcode,
                'country_code' => 'GB',
                'created_by' => 1,
            );
            StoreAddress::create($address_data);
        }
    }
}
