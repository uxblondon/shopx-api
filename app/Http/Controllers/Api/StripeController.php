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
    public function paymentIntend(Request $request)
    {
        try {
            \Stripe\Stripe::setApiKey('sk_test_uK2XwHhWYAhvR1Oh6QN0gmww00vlzitu5m');

            $intent = \Stripe\PaymentIntent::create([
                'description' => $request->get('order_ref'),
                'amount' => $request->get('amount'),
                'currency' => 'gbp',
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'r' => $request->all(), 'message' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'r' => $request->all(), 'secret' => $intent->client_secret]);
    }

    public function payment(Request $request, $order_id)
    {
        try {
            $payment_data = array(
                'payment_id' => $request->get('payment_id'),
                'payment_status' => $request->get('payment_status')
            );

            OrderPayment::where('order_id', $order_id)->update($payment_data);
        } catch (\Exception $e) {

            return response()->json(['status' => 'error', 'r' => $request->all(), 'message' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'r' => $request->all(), 'message' => 'Payment successful.']);
    }
}

//pk_test_UCJ2MxfKldq5aW6wn788nMan00QrabCR6d
//sk_test_uK2XwHhWYAhvR1Oh6QN0gmww00vlzitu5m