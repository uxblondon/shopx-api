<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use App\Models\ShippingZone;
use App\Models\ShippingPackageSize;
use App\Models\ShippingOption;
use App\Models\ProductShippingOption;
use App\Models\ShippingCountry;

class DeliveryOptionController extends Controller
{
    private $packages = [];
    private $address = [];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function options(Request $request)
    {

        // total weight to find the appropriate package

        // total items dimensions to find the package size 

        $this->address = $request->get('address');
        $items = $request->get('items');


        $this->getPackages($items);

        return response()->json(['status' => 'success', 'data' => $this->packages, 'items' => $items, 'country' => $this->address['country']]);
    }



    public function getPackages($items)
    {

        $combined_items = [];
        if (count($items) > 0) {
            foreach ($items as $item) {
                $separated_items = [];
                if ($item['separated_shipping_required'] === 1) {
                    $separated_items[] = $item;
                    $this->packages[] = $this->getDeliveryOptions($separated_items);
                } else {
                    $combined_items[] = $item;
                }
            }

            if (count($combined_items) > 0) {
                $package = $this->getDeliveryOptions($combined_items);
                if ($package['size']) {
                    $this->packages[] = $package;
                } else {
                    $this->splitPackages($combined_items);
                }
            }
        }
    }

    public function getPackageSize($max_width, $max_length, $max_height, $weight, $cbm)
    {

        return ShippingPackageSize::where('width', '>', $max_width)
            ->where('length', '>', $max_length)
            ->where('height', '>', $max_height)
            ->where('min_weight', '<=', $weight)
            ->where('max_weight', '>=', $weight)
            ->where(DB::raw('(width * length * height)'), '>=', $cbm)
            ->first();

        // return [
        //     'max_width' => $max_width,
        //     'max_length' => $max_length,
        //     'max_height' => $max_height,
        //     'weight' => $weight,
        //     'cbm' => $cbm,
        //     'package' => $package,
        // ];
    }

    public function splitPackages($items)
    {
        if (count($items) > 1) {
            $split = array_chunk($items, ceil(count($items) / 2));
            foreach ($split as $splited_package) {
                if (count($splited_package) > 1) {
                    $package = $this->getDeliveryOptions($splited_package);
                    if (count($package['size']) > 0) {
                        $this->packages[] = $package;
                    } else {
                        $this->splitPackages($splited_package);
                    }
                } else {
                    $this->packages[] = $this->getDeliveryOptions($splited_package);
                }
            }
        } else {
            $this->packages[] = $this->getDeliveryOptions($items);
        }
    }


    public function getDeliveryOptions($items)
    {
        $total_value = 0;
        $total_weight = 0;
        $total_cbm = 0;
        foreach ($items as $item) {
            $widths[] = $item['width'];
            $lengths[] = $item['length'];
            $heights[] = $item['height'];
            $total_value += $item['price'] * $item['quantity'];
            $total_weight += $item['weight'] * $item['quantity'];
            $total_cbm += ($item['width'] * $item['length'] * $item['height']) * $item['quantity'];
        }

        if (count($items) > 0) {
            $max_width = max($widths);
            $max_length = max($lengths);
            $max_height = max($heights);

            $size = $this->getPackageSize($max_width, $max_length, $max_height, $total_weight, $total_cbm);

            $options = [];
            if ($size) {

                $shipping_zones = ShippingCountry::where('country_code', $this->address['country'])->distinct()->pluck('shipping_zone_id');

                // check compared with the basket value 
                $options_by_basket_value = ShippingRate::join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
                    ->where('shipping_rates.package_size_id', $size['id'])
                    ->whereIn('shipping_rates.shipping_zone_id', $shipping_zones)
                    ->where('shipping_rates.min_value', '<=', $total_value)
                    ->where('shipping_rates.max_value', '>=', $total_value)
                    ->get()->toArray();

                if (count($options_by_basket_value) > 0) {
                    foreach ($options_by_basket_value as $option) {
                        $options[] = $option;
                    }
                }

                // check the compared with basket weight
                $options_by_basket_weight = ShippingRate::join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
                    ->where('shipping_rates.package_size_id', $size['id'])
                    ->whereIn('shipping_rates.shipping_zone_id', $shipping_zones)
                    ->where('shipping_rates.min_weight', '<=', $total_weight)
                    ->where('shipping_rates.max_weight', '>=', $total_weight)
                    ->get()->toArray();

                if (count($options_by_basket_weight) > 0) {
                    foreach ($options_by_basket_weight as $option) {
                        $options[] = $option;
                    }
                }
            }



            return array(
                'items' => $items,
                'size' => $size,
               // 'shipping_zones' => $shipping_zones,
                'options' => $options,

            );
        }
    }
}
