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

class ShippingOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $zones = ShippingOption::orderBy('provider')->orderBy('service')->get();

        return response()->json(['status' => 'success', 'data' => $zones]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function available()
    {
        $shipping_options = ShippingOption::where('available', 1)->orderBy('provider')->orderBy('service')->get();

        return response()->json(['status' => 'success', 'data' => $shipping_options]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function products($shipping_option_id)
    {

        try {
            $products = ProductShippingOption::join('products', 'products.id', 'product_shipping_options.product_id')
            ->where('shipping_option_id', $shipping_option_id)
                ->orderBy('products.title', 'asc')
                ->get(['products.id', 'product_shipping_options.shipping_option_id', 'products.title'])->toArray();
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to get option products.']);
        }


        return response()->json(['status' => 'success', 'data' => $products]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function manageProducts(Request $request, $shipping_option_id)
    {
        DB::beginTransaction();
        try {
            $products = $request->get('products');

            //  print_r($products);
            // clear all products of shipping zone 
            DB::table('product_shipping_options')->where('shipping_option_id', $shipping_option_id)->delete();
            if (count($products) > 0) {


                $product_shipping_options = [];

                foreach ($products as $product_id) {
                    $product_shipping_options[] = array(
                        'shipping_option_id' => $shipping_option_id,
                        'product_id' => $product_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => auth()->user()->id
                    );
                }

                DB::table('product_shipping_options')->insert($product_shipping_options);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to update shipping option products.']);
        }

        DB::commit();
        return response()->json(['status' => 'success', 'message' => 'Shipping option products successfully updated.']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $shipping_option_data = array(
            'provider' => $request->get('provider'),
            'service' => $request->get('service'),
            'speed' => $request->get('speed'),
            'is_collection' => $request->get('is_collection'),
            'available' => $request->get('available'),
            'note' => $request->get('note'),
            'created_by' => auth()->user()->id
        );

        if($request->get('min_weight') != '') {
            $shipping_option_data['min_weight'] = $request->get('min_weight');
        }

        if($request->get('max_weight') != '') {
            $shipping_option_data['max_weight'] = $request->get('max_weight');
        }

        $has_tracking = $request->get('has_tracking');
        if($has_tracking === 1) {
            $shipping_option_data['has_tracking'] = 1;
            $shipping_option_data['tracking_type'] = $request->get('tracking_type');
        }
        
        $shipping_option = ShippingOption::create($shipping_option_data);

        return response()->json(['status' => 'success', 'message' => 'Package size stored successfully.', 'data' => $shipping_option]);
    }



    

    

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($shipping_zone_id)
    {
        $shipping_zone = ShippingZone::find($shipping_zone_id);
        $shipping_zone->no_of_countries = ShippingCountry::where('shipping_zone_id', $shipping_zone_id)->count();
        $shipping_zone->no_of_rates = ShippingRate::where('shipping_zone_id', $shipping_zone_id)->count();

        return response()->json(['status' => 'success', 'data' => $shipping_zone]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $shipping_option_id)
    {
        try {
            $shipping_option_data = array(
                'provider' => $request->get('provider'),
                'service' => $request->get('service'),
                'speed' => $request->get('speed'),
                'is_collection' => $request->get('is_collection'),
                'available' => $request->get('available'),
                'note' => $request->get('note'),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => auth()->user()->id
            );
    
            if($request->get('min_weight') != '') {
                $shipping_option_data['min_weight'] = $request->get('min_weight');
            }
    
            if($request->get('max_weight') != '') {
                $shipping_option_data['max_weight'] = $request->get('max_weight');
            }
    
            $has_tracking = $request->get('has_tracking');
            if($has_tracking === 1) {
                $shipping_option_data['has_tracking'] = 1;
                $shipping_option_data['tracking_type'] = $request->get('tracking_type');
            }
    
            ShippingOption::where('id', $shipping_option_id)->update($shipping_option_data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to update package size.']);
        }

        $shipping_option = ShippingOption::where('id', $shipping_option_id)->first();

        return response()->json(['status' => 'success', 'message' => 'Package size successfully updated.', 'data' => $shipping_option]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($shipping_option_id)
    {
        //TODO - conditions 
        try {
            ShippingOption::where('id', $shipping_option_id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => auth()->user()->id,
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to delete package size.']);
        }
        return response()->json(['status' => 'success', 'message' => 'Package size successfully deleted.']);
    }
}
