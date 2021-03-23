<?php

use App\Models\ShippingCountry;
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
        $products = DB::table('products')->get();

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
            $shipping_option = ShippingOption::create($shipping_option);

            // product shipping options 
            $product_shipping_options = [];
            foreach ($products as $product) {
                $product_shipping_options[] = array(
                    'product_id' => $product->id,
                    'shipping_option_id' => $shipping_option->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => 1,
                );
            }
            DB::table('product_shipping_options')->insert($product_shipping_options);
        }




        $zones = array(
            'Africa' => [
                "DZ",
                "AO",
                "BW",
                "BI",
                "CM",
                "CV",
                "CF",
                "TD",
                "KM",
                "YT",
                "CG",
                "CD",
                "BJ",
                "GQ",
                "ET",
                "ER",
                "DJ",
                "GA",
                "GM",
                "GH",
                "GN",
                "CI",
                "KE",
                "LS",
                "LR",
                "LY",
                "MG",
                "MW",
                "ML",
                "MR",
                "MU",
                "MA",
                "MZ",
                "NA",
                "NE",
                "NG",
                "GW",
                "RE",
                "RW",
                "SH",
                "ST",
                "SN",
                "SC",
                "SL",
                "SO",
                "ZA",
                "ZW",
                "SS",
                "EH",
                "SD",
                "SZ",
                "TG",
                "TN",
                "UG",
                "EG",
                "TZ",
                "BF",
                "ZM",
            ],

            'Antarctica' => ["AQ", "BV", "GS", "TF", "HM"],

            'Asia' => [
                "AF",
                "AZ",
                "BH",
                "BD",
                "AM",
                "BT",
                "IO",
                "BN",
                "MM",
                "KH",
                "LK",
                "CN",
                "TW",
                "CX",
                "CC",
                "CY",
                "GE",
                "PS",
                "HK",
                "IN",
                "ID",
                "IR",
                "IQ",
                "IL",
                "JP",
                "KZ",
                "JO",
                "KP",
                "KR",
                "KW",
                "KG",
                "LA",
                "LB",
                "MO",
                "MY",
                "MV",
                "MN",
                "OM",
                "NP",
                "PK",
                "PH",
                "TL",
                "QA",
                "RU",
                "SA",
                "SG",
                "VN",
                "SY",
                "TJ",
                "TH",
                "AE",
                "TR",
                "TM",
                "UZ",
                "YE",
                "XE",
                "XD",
            ],

            'Europe' => [
                "AL",
                "AD",
                "AZ",
                "AT",
                "AM",
                "BE",
                "BA",
                "BG",
                "BY",
                "HR",
                "CY",
                "CZ",
                "DK",
                "EE",
                "FO",
                "FI",
                "AX",
                "FR",
                "GE",
                "DE",
                "GI",
                "GR",
                "VA",
                "HU",
                "IS",
                "IE",
                "IT",
                "KZ",
                "LV",
                "LI",
                "LT",
                "LU",
                "MT",
                "MC",
                "MD",
                "ME",
                "NL",
                "NO",
                "PL",
                "PT",
                "RO",
                "RU",
                "SM",
                "RS",
                "SK",
                "SI",
                "ES",
                "SJ",
                "SE",
                "CH",
                "TR",
                "UA",
                "MK",
                "GB",
                "GG",
                "JE",
                "IM",
            ],

            'North America' => [
                "AG",
                "BS",
                "BB",
                "BM",
                "BZ",
                "VG",
                "CA",
                "KY",
                "CR",
                "CU",
                "DM",
                "DO",
                "SV",
                "GL",
                "GD",
                "GP",
                "GT",
                "HT",
                "HN",
                "JM",
                "MQ",
                "MX",
                "MS",
                "AN",
                "CW",
                "AW",
                "SX",
                "BQ",
                "NI",
                "UM",
                "PA",
                "PR",
                "BL",
                "KN",
                "AI",
                "LC",
                "MF",
                "PM",
                "VC",
                "TT",
                "TC",
                "US",
                "VI",
            ],

            'Oceania' => [
                "AS",
                "AU",
                "SB",
                "CK",
                "FJ",
                "PF",
                "KI",
                "GU",
                "NR",
                "NC",
                "VU",
                "NZ",
                "NU",
                "NF",
                "MP",
                "UM",
                "FM",
                "MH",
                "PW",
                "PG",
                "PN",
                "TK",
                "TO",
                "TV",
                "WF",
                "WS",
                "XX",
            ],

            'South America' => [
                "AR",
                "BO",
                "BR",
                "CL",
                "CO",
                "EC",
                "FK",
                "GF",
                "GY",
                "PY",
                "PE",
                "SR",
                "UY",
                "VE",
            ],

            'Collection' => [],
            'Domestic' => ['GB']
        );


        foreach ($zones as $zone => $countries) {
            $zone_data = array(
                'title' => $zone,
                'available' => 1,
                'created_by' => 1
            );

            $shipping_zone = ShippingZone::create($zone_data);

            if (count($countries) > 0) {
                foreach ($countries as $country) {
                    $shipping_country_data = [
                        'shipping_zone_id' => $shipping_zone->id,
                        'country_code' => $country,
                        'created_by' => 1,
                    ];
                    ShippingCountry::create($shipping_country_data);
                }
            }


            // shipping zone products 
            $shipping_zone_products = [];
            foreach ($products as $product) {
                $shipping_zone_products[] = array(
                    'shipping_zone_id' => $shipping_zone->id,
                    'product_id' => $product->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => 1,
                );
            }
            DB::table('shipping_zone_products')->insert($shipping_zone_products);


        }

        
    }
}
