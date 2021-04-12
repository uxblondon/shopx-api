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
use App\Models\CollectionRate;

class CollectionOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function options(Request $request)
    {
        $collection_point_id = $request->get('collection_point_id');

        $collection_rates = CollectionRate::where('collection_point_id', $collection_point_id)->orderBy('cost')->get()->toArray();

        return response()->json(['status' => 'success', 'data' => $collection_rates]);
    }
}
