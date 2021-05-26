<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderPayment;
use App\Models\Order;

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
                'metadata' => [
                    'order_id' => $request->get('order_id'),
                ]
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

            $payment = OrderPayment::where('order_id', $order_id)->update($payment_data);

            if($payment && $request->get('payment_status') === 'succeeded') {
                Order::where('id', $order_id)->update(['status' => 'confirmed']);
            }

        } catch (\Exception $e) {

            return response()->json(['status' => 'error', 'r' => $request->all(), 'message' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'r' => $request->all(), 'message' => 'Payment successful.']);
    }

    public function paymentStatus()
    {
        try {

            $input = @file_get_contents("php://input");
          //  $data = json_decode($input);

            DB::table('test')->insert(['data' => $input]);
           // $charge_id = $data->data->object->id;
           // $this->stripe->paymentSucceeded($charge_id);
            
        } catch (\Stripe\Error\Base $ex) {

            $event = (object) array(
                        'event_id' => time(),
                        'type' => 'error',
                        'data' => array('error' => $ex->getMessage())
            );
            
          //  $this->stripe->recordEvent($event);
        }

        return response()->json(['msg' => 'stripe payment succeeded'], 200);
    }
}

//pk_test_UCJ2MxfKldq5aW6wn788nMan00QrabCR6d
//sk_test_uK2XwHhWYAhvR1Oh6QN0gmww00vlzitu5m