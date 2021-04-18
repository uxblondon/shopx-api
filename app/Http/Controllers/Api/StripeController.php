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
use App\Models\ShippingCountry;

class StripeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function secret(Request $request)
    {
        try {
            \Stripe\Stripe::setApiKey('sk_test_uK2XwHhWYAhvR1Oh6QN0gmww00vlzitu5m');

            $intent = \Stripe\PaymentIntent::create([
              'description' => $request->get('order_ref'),
              'amount' => $request->get('amount'),
              'currency' => 'gbp',
            ]);
        } catch(\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'client_secret' => $intent->client_secret]);
    }

}

//pk_test_UCJ2MxfKldq5aW6wn788nMan00QrabCR6d
//sk_test_uK2XwHhWYAhvR1Oh6QN0gmww00vlzitu5m