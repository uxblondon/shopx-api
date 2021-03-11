<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use App\Models\ShippingZone;

class ShippingZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $zones = ShippingZone::orderBy('available', 'desc')->orderBy('title')->get();

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

        $shipping_zone = ShippingZone::find($shipping_zone_id);
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
