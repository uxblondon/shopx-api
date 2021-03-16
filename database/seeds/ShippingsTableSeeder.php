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
            'Domestic',
            'European Union',
            'Worldwide',
        );

        foreach ($zones as $zone) {
            $zone_data = array(
                'title' => $zone,
                'available' => 1,
                'created_by' => 1
            );

            $shipping_zone = ShippingZone::create($zone_data);

            // $ranges = array(
            //     ['from' => 0, 'to' => 100, 'rate' => 0.66],
            //     ['from' => 101, 'to' => 750, 'rate' => 0.96],
            //     ['from' => 751, 'to' => 2000, 'rate' => 3],
            //     ['from' => 2001, 'to' => 20000, 'rate' => 5.1],
            //     ['from' => 20001, 'to' => 30000, 'rate' => 12.12],
            // );

            // foreach ($ranges as $range) {
            //     $shipping_rate_data = array(
            //         'shipping_zone_id' => $shipping_zone->id,
            //         'weight_from' => $range['from'],
            //         'weight_upto' => $range['to'],
            //         'rate' => $range['rate'],
            //         'available' => 1,
            //         'created_by' => 1
            //     );

            //     ShippingRate::create($shipping_rate_data);
            // }

            // Letter 100g 24 x 16.5 x 0.5 cm
            // Large letter 750g 35.3 x 25 x 2.5 cm
            //Small parcel 2k 45 x 35 x 16 cm
            //Medium parcel 20kg 61 x 46 x 46 cm
            //Medium tube 20kg 90 x 25 x 25 cm


            // Royal Mail
            // 1st Class
            // More details
            // 1 day delivery aim	Up to £20 for loss or damage	Not Tracked	
            // 85p*
            // 85p*
            // Buy online
            // Royal Mail
            // 2nd Class
            // More details
            // 3 days delivery aim	Up to £20 for loss or damage	Not Tracked	
            // 66p*
            // 66p*
            // Buy online
            // Royal Mail
            // Signed For® 1st Class
            // More details
            // 1 day delivery aim	Up to £50 for loss or damage	Proof of Delivery	
            // £2.25*
            // £2.25*
            // Buy online
            // Royal Mail
            // Signed For® 2nd Class
            // More details
            // 3 days delivery aim	Up to £50 for loss or damage	Proof of Delivery	
            // £2.06*
            // £2.06*
            // Buy online
            // Royal Mail
            // Special Delivery Guaranteed by 1pm®
            // More details
            // Guaranteed by 1pm next day	Up to £500 for loss or damage	Tracked	
            // £6.85*
            // £6.75*
            // Buy online
            // Royal Mail
            // Special Delivery Guaranteed by 9am®
            // More details
            // Guaranteed by 9am next day	Up to £50 for loss or damage	Tracked	
            // £22.26
            // Only available at a Post Office®
            // Parcelforce Worldwide
            // express48



        }
    }
}
