<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use App\Models\ShippingZone;
use App\Models\ShippingCountry;

class ShippingZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $zones = ShippingZone::leftJoin('shipping_countries', 'shipping_countries.shipping_zone_id', 'shipping_zones.id')
            ->select('shipping_zones.id', 'shipping_zones.title', DB::raw('count(DISTINCT shipping_countries.id) as no_of_countries'), 'shipping_zones.available')
            ->orderBy('available', 'desc')
            ->orderBy('title')
            ->groupBy('shipping_zones.id')
            ->get();

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
        $shipping_zone_data = array(
            'title' => $request->get('title'),
            'available' => $request->get('available'),
            'created_by' => auth()->user()->id
        );

        $shipping_zone = ShippingZone::create($shipping_zone_data);

        return response()->json(['status' => 'success', 'data' => $shipping_zone]);
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
    public function update(Request $request, $shipping_zone_id)
    {
        try {
            $shipping_zone_data = array(
                'title' => $request->get('title'),
                'available' => $request->get('available'),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => auth()->user()->id,
            );

            ShippingZone::where('id', $shipping_zone_id)->update($shipping_zone_data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update shipping zone.']);
        }

        $shipping_zone = ShippingZone::leftJoin('shipping_countries', 'shipping_countries.shipping_zone_id', 'shipping_zones.id')
            ->where('shipping_zones.id', $shipping_zone_id)
            ->select('shipping_zones.id', 'shipping_zones.title', DB::raw('count(DISTINCT shipping_countries.id) as no_of_countries'), 'shipping_zones.available')
            ->orderBy('available', 'desc')
            ->orderBy('title')
            ->groupBy('shipping_zones.id')
            ->first();

        return response()->json(['status' => 'success', 'message' => 'Shipping zone successfully updated.', 'data' => $shipping_zone]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($shipping_zone_id)
    {
        try {
            ShippingRate::where('shipping_zone_id', $shipping_zone_id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => auth()->user()->id,
            ]);

            ShippingZone::where('id', $shipping_zone_id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => auth()->user()->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to delete shipping zone.']);
        }
        return response()->json(['status' => 'success', 'message' => 'Shipping zone successfully deleted.']);
    }
}
