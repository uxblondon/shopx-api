<?php

namespace App\Services;

use DB;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingPackageSize;
use App\Models\ProductShippingOption;
use App\Models\ShippingZoneProduct;
use App\Models\ShippingCountry;

class DeliveryQuote
{
    private $packages = [];
    private $shipping_zone = false;

    // Preserve the existing package splitting and size boundaries for the shop.
    public function packages(array $items, string $country): array
    {
        $this->packages = [];
        $zone = ShippingCountry::where('country_code', $country)->first();
        $this->shipping_zone = $zone && ShippingZone::where('id', $zone->shipping_zone_id)->where('available', 1)->exists()
            ? $zone->shipping_zone_id : false;
        $this->getPackages($items);
        return $this->packages;
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

        return ShippingPackageSize::where('available', 1)->where('width', '>', $max_width)
            ->where('length', '>', $max_length)
            ->where('height', '>', $max_height)
            ->where('min_weight', '<=', $weight)
            ->where('max_weight', '>=', $weight)
            ->where(DB::raw('(width * length * height)'), '>=', $cbm)
            ->orderBy('id')->first();

    }

    public function splitPackages($items)
    {
        if (count($items) > 1) {
            $split = array_chunk($items, (int) ceil(count($items) / 2));
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
            $total_value += CheckoutMoney::pence($item['price']) * $item['quantity'];
            $total_weight += $item['weight'] * $item['quantity'];
            $total_cbm += ($item['width'] * $item['length'] * $item['height']) * $item['quantity'];
            $total_additional_shipping_cost += CheckoutMoney::pence($item['additional_shipping_cost']) * $item['quantity'];

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
                    ->where('shipping_rates.available', 1)
                    ->where('shipping_options.available', 1)
                    ->whereNull('shipping_options.deleted_at')
                    ->where('cost_based_on', 'basket_value')
                    ->where('shipping_rates.shipping_zone_id', $this->shipping_zone)
                    ->where('shipping_rates.min_value', '<=', CheckoutMoney::pounds($total_value))
                    ->where('shipping_rates.max_value', '>=', CheckoutMoney::pounds($total_value))
                    ->whereIn('shipping_rates.shipping_option_id', $available_shipping_options)
                    ->orderBy('shipping_rates.id')->get(['shipping_rates.*', 'shipping_rates.id as rate_id', 'shipping_options.id as id', 'shipping_options.provider', 'shipping_options.service', 'shipping_options.speed'])->toArray();

                if (count($options_by_basket_value) > 0) {
                    foreach ($options_by_basket_value as $option) {
                        $option['cost'] = CheckoutMoney::pounds(CheckoutMoney::pence($option['cost']) + $total_additional_shipping_cost);
                        $options[] = $option;
                    }
                }

                // check the compared with basket weight
                $options_by_basket_weight = ShippingRate::join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
                    ->where('shipping_rates.package_size_id', $size['id'])
                    ->where('shipping_rates.available', 1)
                    ->where('shipping_options.available', 1)
                    ->whereNull('shipping_options.deleted_at')
                    ->where('cost_based_on', 'basket_weight')
                    ->where('shipping_rates.shipping_zone_id', $this->shipping_zone)
                    ->where('shipping_rates.min_weight', '<=', $total_weight)
                    ->where('shipping_rates.max_weight', '>=', $total_weight)
                    ->whereIn('shipping_rates.shipping_option_id', $available_shipping_options)
                    ->orderBy('shipping_rates.id')->get(['shipping_rates.*', 'shipping_rates.id as rate_id', 'shipping_options.id as id', 'shipping_options.provider', 'shipping_options.service', 'shipping_options.speed'])->toArray();

                if (count($options_by_basket_weight) > 0) {
                    foreach ($options_by_basket_weight as $option) {
                        $option['cost'] = CheckoutMoney::pounds(CheckoutMoney::pence($option['cost']) + $total_additional_shipping_cost);
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
