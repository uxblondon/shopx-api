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

        $shipping_package_size_data = array(
            'format' => $request->get('format'),
            'length' => $request->get('length'),
            'width' => $request->get('width'),
            'height' => $request->get('height'),
            'min_weight' => $request->get('min_weight'),
            'max_weight' => $request->get('max_weight'),
            'remark' => $request->get('remark'),
            'available' => $request->get('available'),
            'created_by' => auth()->user()->id
        );

        $shipping_package_size = ShippingPackageSize::create($shipping_package_size_data);

        return response()->json(['status' => 'success', 'message' => 'Package size stored successfully.', 'data' => $shipping_package_size]);
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
    public function update(Request $request, $shipping_package_size_id)
    {
        try {
            $shipping_package_size_data = array(
                'format' => $request->get('format'),
                'length' => $request->get('length'),
                'width' => $request->get('width'),
                'height' => $request->get('height'),
                'min_weight' => $request->get('min_weight'),
                'max_weight' => $request->get('max_weight'),
                'remark' => $request->get('remark'),
                'available' => $request->get('available'),
                'created_by' => auth()->user()->id
            );
    
            ShippingPackageSize::where('id', $shipping_package_size_id)->update($shipping_package_size_data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update package size.']);
        }

        $shipping_package_size = ShippingPackageSize::where('id', $shipping_package_size_id)->first();

        return response()->json(['status' => 'success', 'message' => 'Package size successfully updated.', 'data' => $shipping_package_size]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($shipping_package_size_id)
    {
        //TODO - conditions 
        try {
            ShippingPackageSize::where('id', $shipping_package_size_id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => auth()->user()->id,
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to delete package size.']);
        }
        return response()->json(['status' => 'success', 'message' => 'Package size successfully deleted.']);
    }
}
