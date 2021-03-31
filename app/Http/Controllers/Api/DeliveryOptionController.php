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

class DeliveryOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function options(Request $request)
    {
        return response()->json(['status' => 'success', 'data' => [
            'option 1',
            'option 2',
            'option 3'
        ]]);
    }
}
