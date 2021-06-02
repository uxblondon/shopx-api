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
use App\Models\ShippingZoneProduct;
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
        $this->address = $request->get('address');
        $shipping_zone = ShippingCountry::where('country_code', $this->address['country'])
            ->first(['shipping_zone_id']);

        $this->shipping_zone = isset($shipping_zone['shipping_zone_id']) ? $shipping_zone['shipping_zone_id'] : false; 
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
                    if (isset($package['size']) && $package['size']) {
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
        // available shipping options
        $options = [];
        $available_shipping_options = [];
        $total_value = 0;
        $total_weight = 0;
        $total_cbm = 0;
        $total_additional_shipping_cost = 0;
        $item_shipping_options = [];
        foreach ($items as $item) {
            $widths[] = $item['width'];
            $lengths[] = $item['length'];
            $heights[] = $item['height'];
            $total_value += $item['price'] * $item['quantity'];
            $total_weight += $item['weight'] * $item['quantity'];
            $total_cbm += ($item['width'] * $item['length'] * $item['height']) * $item['quantity'];
            $total_additional_shipping_cost += $item['additional_shipping_cost'] * $item['quantity'];

            // check shipping zone availability 
            $available_shipping_zones = ShippingZoneProduct::where('product_id', $item['product_id'])->pluck('shipping_zone_id')->toArray();
            if (!in_array($this->shipping_zone, (array)$available_shipping_zones)) {
                return array(
                    'items' => $items,
                    'size' => false,
                    'options' => [],
                    'message' => 'Unfortunately we are not able to deliver ' . $item['title'] . ' to your address.',
                );
            }

            // shipping options 
            $item_shipping_options[] = ProductShippingOption::where('product_id', $item['product_id'])->pluck('shipping_option_id')->toArray();
        }

        if(count($item_shipping_options) > 1) {
            $available_shipping_options = array_intersect(...$item_shipping_options);
        } else {
            $available_shipping_options = $item_shipping_options[0];
        }
       
        if (count((array)$items) > 0) {
            $max_width = max($widths);
            $max_length = max($lengths);
            $max_height = max($heights);

            $size = $this->getPackageSize($max_width, $max_length, $max_height, $total_weight, $total_cbm);

            if ($size) {
                //check compared with the basket value 
                $options_by_basket_value = ShippingRate::join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
                    ->where('shipping_rates.package_size_id', $size['id'])
                    ->where('cost_based_on', 'basket_value')
                    ->where('shipping_rates.shipping_zone_id', $this->shipping_zone)
                    ->where('shipping_rates.min_value', '<=', $total_value)
                    ->where('shipping_rates.max_value', '>=', $total_value)
                    ->whereIn('shipping_rates.shipping_option_id', $available_shipping_options)
                    ->get()->toArray();

                if (count($options_by_basket_value) > 0) {
                    foreach ($options_by_basket_value as $option) {
                        $option['cost'] += $total_additional_shipping_cost;
                        $options[] = $option;
                    }
                }

                // check the compared with basket weight
                $options_by_basket_weight = ShippingRate::join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
                    ->where('shipping_rates.package_size_id', $size['id'])
                    ->where('cost_based_on', 'basket_weight')
                    ->where('shipping_rates.shipping_zone_id', $this->shipping_zone)
                    ->where('shipping_rates.min_weight', '<=', $total_weight)
                    ->where('shipping_rates.max_weight', '>=', $total_weight)
                    ->whereIn('shipping_rates.shipping_option_id', $available_shipping_options)
                    ->get()->toArray();

                if (count($options_by_basket_weight) > 0) {
                    foreach ($options_by_basket_weight as $option) {
                        $option['cost'] += $total_additional_shipping_cost;
                        $options[] = $option;
                    }
                }
                return array(
                    'items' => $items,
                    'size' => $size,
                    'options' => $options,
                    'message' => count($options) === 0 ? 'No delivery option found. Please contact Trinity House.' : '',
    
                );
            }

            return array(
                'items' => $items,
                'size' => false,
                'options' => [],
                'message' => 'No delivery option found. Please contact Trinity House.',
            );
        }
    }
}
