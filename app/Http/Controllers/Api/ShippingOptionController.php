<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use App\Models\ShippingZone;
use App\Models\ShippingPackageSize;
use App\Models\ShippingOption;

class ShippingOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $zones = ShippingOption::orderBy('name', 'asc')->get();

        return response()->json(['status' => 'success', 'data' => $zones]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function available()
    {
        $zones = ShippingZone::where('available', 1)->orderBy('available', 'desc')->orderBy('title')->get();

        return response()->json(['status' => 'success', 'data' => $zones]);
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
            'name' => $request->get('name'),
            'min_weight' => $request->get('min_weight'),
            'max_weight' => $request->get('max_weight'),
            'remark' => $request->get('remark'),
            'is_collection' => $request->get('is_collection'),
            'available' => $request->get('available'),
            'created_by' => auth()->user()->id
        );

        $shipping_option = ShippingOption::create($shipping_option_data);

        return response()->json(['status' => 'success', 'message' => 'Package size stored successfully.', 'data' => $shipping_option]);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function countries($shipping_zone_id)
    {

        try {
            $countries = ShippingCountry::where('shipping_zone_id', $shipping_zone_id)
                ->orderBy('label', 'asc')
                ->get(['code', 'label'])->toArray();
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to get zone countries.']);
        }


        return response()->json(['status' => 'success', 'data' => $countries]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function manageShippingCountries(Request $request, $shipping_zone_id)
    {

        DB::beginTransaction();
        try {
            $countries = $request->get('shipping_countries');

            //  print_r($countries);

            if (count($countries) > 0) {
                // clear all countries of shipping zone 
                DB::table('shipping_countries')->where('shipping_zone_id', $shipping_zone_id)->delete();

                $shipping_countries = [];

                foreach ($countries as $country) {
                    $shipping_countries[] = array(
                        'shipping_zone_id' => $shipping_zone_id,
                        'code' => $country['code'],
                        'label' => $country['label'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => auth()->user()->id
                    );
                }

                DB::table('shipping_countries')->insert($shipping_countries);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to update shipping zone countries.']);
        }

        DB::commit();
        return response()->json(['status' => 'success', 'message' => 'Shipping zone countries successfully updated.']);
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
    public function update(Request $request, $shipping_option_id)
    {
        try {
            $shipping_option_data = array(
                'name' => $request->get('name'),
                'min_weight' => $request->get('min_weight'),
                'max_weight' => $request->get('max_weight'),
                'remark' => $request->get('remark'),
                'is_collection' => $request->get('is_collection'),
                'available' => $request->get('available'),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => auth()->user()->id
            );
    
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
