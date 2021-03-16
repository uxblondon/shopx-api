<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\ShippingRate;
use App\Models\ShippingZone;

class ShippingRateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($shipping_zone_id)
    {
        try {
            
            $rates = ShippingRate::join('shipping_package_sizes', 'shipping_package_sizes.id', 'shipping_rates.package_size_id')
            ->join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
            ->where('shipping_zone_id', $shipping_zone_id)
            ->orderBy('shipping_rates.package_size_id')
            ->orderBy('shipping_rates.shipping_option_id')
            ->orderBy('shipping_rates.min_weight')
            ->orderBy('shipping_rates.min_value')
            ->get([
                'shipping_rates.id',
                'shipping_rates.shipping_zone_id',
                'shipping_rates.package_size_id',
                'shipping_package_sizes.format as package',
                'shipping_rates.shipping_option_id',
                'shipping_options.name as shipping_option',
                'shipping_rates.cost_based_on',
                'shipping_rates.min_value',
                'shipping_rates.max_value',
                'shipping_rates.min_weight',
                'shipping_rates.max_weight',
                'shipping_rates.cost',
                'shipping_rates.available',
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'data' => $rates]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $shipping_zone_id)
    {
        try {
            $cost_based_on = $request->get('cost_based_on');
            $shipping_rate_data = array(
                'shipping_zone_id' => $shipping_zone_id,
                'package_size_id' => $request->get('package_size_id'),
                'shipping_option_id' => $request->get('shipping_option_id'),
                'cost_based_on' => $cost_based_on,
                'cost' => $request->get('cost'),
                'available' => $request->get('available'),
                'created_by' => auth()->user()->id
            );

            if($cost_based_on === 'basket_weight') {
                $shipping_rate_data['min_weight'] = $request->get('min_weight');
                $shipping_rate_data['max_weight'] = $request->get('max_weight');
            } elseif ($cost_based_on === 'basket_value') {
                $shipping_rate_data['min_value'] = $request->get('min_value');
                $shipping_rate_data['max_value'] = $request->get('max_value');
            } elseif($cost_based_on === 'basket_weight_and_value') {
                $shipping_rate_data['min_value'] = $request->get('min_value');
                $shipping_rate_data['max_value'] = $request->get('max_value');
                $shipping_rate_data['min_weight'] = $request->get('min_weight');
                $shipping_rate_data['max_weight'] = $request->get('max_weight');
            }
    
            $rate = ShippingRate::create($shipping_rate_data);

            $shipping_rate = ShippingRate::join('shipping_package_sizes', 'shipping_package_sizes.id', 'shipping_rates.package_size_id')
            ->join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
            ->where('shipping_rates.id', $rate->id)
            ->first([
                'shipping_rates.id',
                'shipping_rates.shipping_zone_id',
                'shipping_rates.package_size_id',
                'shipping_package_sizes.format as package',
                'shipping_rates.shipping_option_id',
                'shipping_options.name as shipping_option',
                'shipping_rates.cost_based_on',
                'shipping_rates.min_value',
                'shipping_rates.max_value',
                'shipping_rates.min_weight',
                'shipping_rates.max_weight',
                'shipping_rates.cost',
                'shipping_rates.available',
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to store shipping rate.']);
        }

        return response()->json(['status' => 'success', 'message' => 'Shipping rate successfully stored.', 'data' => $shipping_rate]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $shipping_zone_id, $shipping_rate_id)
    {
        try {
                $cost_based_on = $request->get('cost_based_on');
                $shipping_rate_data = array(
                    'package_size_id' => $request->get('package_size_id'),
                    'shipping_option_id' => $request->get('shipping_option_id'),
                    'cost_based_on' => $cost_based_on,
                    'cost' => $request->get('cost'),
                    'available' => $request->get('available'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => auth()->user()->id
                );
    
                if($cost_based_on === 'basket_weight') {
                    $shipping_rate_data['min_weight'] = $request->get('min_weight');
                    $shipping_rate_data['max_weight'] = $request->get('max_weight');
                    $shipping_rate_data['min_value'] = NULL;
                    $shipping_rate_data['max_value'] = NULL;
                } elseif ($cost_based_on === 'basket_value') {
                    $shipping_rate_data['min_weight'] = NULL;
                    $shipping_rate_data['max_weight'] = NULL;
                    $shipping_rate_data['min_value'] = $request->get('min_value');
                    $shipping_rate_data['max_value'] = $request->get('max_value');
                } elseif($cost_based_on === 'basket_weight_and_value') {
                    $shipping_rate_data['min_value'] = $request->get('min_value');
                    $shipping_rate_data['max_value'] = $request->get('max_value');
                    $shipping_rate_data['min_weight'] = $request->get('min_weight');
                    $shipping_rate_data['max_weight'] = $request->get('max_weight');
                }
        
                ShippingRate::where('id', $shipping_rate_id)->where('shipping_zone_id', $shipping_zone_id)->update($shipping_rate_data);
    
                $shipping_rate = ShippingRate::join('shipping_package_sizes', 'shipping_package_sizes.id', 'shipping_rates.package_size_id')
                ->join('shipping_options', 'shipping_options.id', 'shipping_rates.shipping_option_id')
                ->where('shipping_rates.id', $shipping_rate_id)
                ->first([
                    'shipping_rates.id',
                    'shipping_rates.shipping_zone_id',
                    'shipping_rates.package_size_id',
                    'shipping_package_sizes.format as package',
                    'shipping_rates.shipping_option_id',
                    'shipping_options.name as shipping_option',
                    'shipping_rates.cost_based_on',
                    'shipping_rates.min_value',
                    'shipping_rates.max_value',
                    'shipping_rates.min_weight',
                    'shipping_rates.max_weight',
                    'shipping_rates.cost',
                    'shipping_rates.available',
                ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to update shipping rate.']);
        }

        return response()->json(['status' => 'success', 'message' => 'Shipping rate successfully updated.', 'data' => $shipping_rate]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($shipping_zone_id, $shipping_rate_id)
    {
        try {
            ShippingRate::where('id', $shipping_rate_id)->where('shipping_zone_id', $shipping_zone_id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => auth()->user()->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to delete shipping rate.']);
        }
        return response()->json(['status' => 'success', 'message' => 'Shipping rate successfully deleted.']);
    }
}
