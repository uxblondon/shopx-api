<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutCatalogue;
use App\Services\DeliveryQuote;
use Illuminate\Http\Request;

class DeliveryOptionController extends Controller
{
    public function options(Request $request)
    {
        $request->validate(['address.country' => 'required|string|size:2|alpha']);
        $country = strtoupper($request->input('address.country'));
        $items = app(CheckoutCatalogue::class)->items($request->input('items'));
        $packages = app(DeliveryQuote::class)->packages($items, $country);
        return response()->json(['status' => 'success', 'data' => $packages, 'items' => $items, 'country' => $country]);
    }
}
