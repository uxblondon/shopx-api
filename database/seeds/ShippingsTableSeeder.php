<?php

use App\Models\ShippingZone;
use App\Models\ShippingPackageSize;
use App\Models\ShippingOption;
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
        }

         // Letter 100g 24 x 16.5 x 0.5 cm
            // Large letter 750g 35.3 x 25 x 2.5 cm
            //Small parcel 2k 45 x 35 x 16 cm
            //Medium parcel 20kg 61 x 46 x 46 cm
            //Medium tube 20kg 90 x 25 x 25 cm

            $package_sizes = array(
                ['format' => 'Letter', 'length' => 24, 'width' => 16.5, 'height' => 0.5, 'min_weight' => 0, 'max_weight' => 100],
                ['format' => 'Large letter', 'length' => 35.3, 'width' => 25, 'height' => 2.5, 'min_weight' => 0, 'max_weight' => 750],
                ['format' => 'Small parcel', 'length' => 45, 'width' => 35, 'height' => 16, 'min_weight' => 0, 'max_weight' => 2000],
                ['format' => 'Medium parcel', 'length' => 61, 'width' => 46, 'height' => 46, 'min_weight' => 0, 'max_weight' => 20000],
                ['format' => 'Medium tube', 'length' => 90, 'width' => 25, 'height' => 25, 'min_weight' => 0, 'max_weight' => 20000],
            );

            foreach ($package_sizes as $package_size) {
                $package_size['available'] = 1;
                $package_size['created_by'] = 1;
                ShippingPackageSize::create($package_size);
            }

            $shipping_options = array(
                ['provider' => 'Store', 'service' => 'Collection', 'speed' => '1-3 days', 'has_tracking' => 0, 'tracking_type' => '', 'min_weight' => 0, 'max_weight' => 20000, 'is_collection' => 1],
                ['provider' => 'Royal Mail', 'service' => '1st Class', 'speed' => '1 day delivery', 'has_tracking' => 0, 'tracking_type' => '', 'min_weight' => 0, 'max_weight' => 100],
                ['provider' => 'Royal Mail', 'service' => '2nd Class', 'speed' => '3 days delivery', 'has_tracking' => 0, 'tracking_type' => '', 'min_weight' => 0, 'max_weight' => 750],
                ['provider' => 'Royal Mail', 'service' => 'Special Delivery Guaranteed', 'speed' => 'Next working day delivery', 'has_tracking' => 1, 'tracking_type' => 'Tracked', 'min_weight' => 0, 'max_weight' => 2000],
                ['provider' => 'Fedex', 'service' => 'Worldwide parcel', 'speed' => '3-5 days delivery', 'has_tracking' => 0, 'tracking_type' => '', 'min_weight' => 0, 'max_weight' => 20000],
                ['provider' => 'USPS', 'service' => 'USA parcel', 'speed' => '1-2 weeks delivery', 'has_tracking' => 0, 'tracking_type' => '', 'min_weight' => 0, 'max_weight' => 20000],
                ['provider' => 'Parcelforce Worldwide', 'service' => 'Worldwide large parcel', 'speed' => '3-4 weeks delivery', 'has_tracking' => 1, 'tracking_type' => 'Proof of Delivery', 'min_weight' => 0, 'max_weight' => 20000],
            );

            foreach ($shipping_options as $shipping_option) {
                $shipping_option['available'] = 1;
                $shipping_option['created_by'] = 1;
                ShippingOption::create($shipping_option);
            }
        
    }
}
