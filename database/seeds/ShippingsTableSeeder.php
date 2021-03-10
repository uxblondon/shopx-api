<?php

use App\Models\ShippingZone;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

class ShippingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $zones = array(
            'Collection',
            'UK',
            'EU',
            'Worldwide',
        );

        foreach ($zones as $zone) {
            $zone_data = array(
                'title' => $zone,
                'available' => 1,
                'created_by' => 1
            );

            $shipping_zone = ShippingZone::create($zone_data);

            $ranges = array(
                ['from' => 0, 'to' => 100, 'rate' => 0.66],
                ['from' => 101, 'to' => 750, 'rate' => 0.96],
                ['from' => 751, 'to' => 2000, 'rate' => 3],
                ['from' => 2001, 'to' => 20000, 'rate' => 5.1],
                ['from' => 20001, 'to' => 30000, 'rate' => 12.12],
            );

            foreach ($ranges as $range) {
                $shipping_rate_data = array(
                    'shipping_zone_id' => $shipping_zone->id,
                    'weight_from' => $range['from'],
                    'weight_upto' => $range['to'],
                    'rate' => $range['rate'],
                    'available' => 1,
                    'created_by' => 1
                );

                ShippingRate::create($shipping_rate_data);
            }
        }
    }
}
