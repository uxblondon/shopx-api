<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderPayment;
use App\Models\Order;

class PaypalController extends Controller
{


    public function paymentStatus()
    {
        try {
            $input = @file_get_contents("php://input");
            $data = json_decode($input);
            $type = $data->event_type;
            if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
                $payment = $data->resource;
                if ($payment->status === 'COMPLETED') {
                    OrderPayment::where('payment_id', $payment->id)
                    ->update(['payment_status' => $payment->status, 'payment_confirmed' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
                }
            }

            DB::table('test')->insert(['data' => $input]);
        } catch (\Exception $e) {
            $event = (object) array(
                'event_id' => time(),
                'type' => 'error',
                'data' => array('error' => $e->getMessage())
            );
        }

        return response()->json(['msg' => 'paypal payment hook'], 200);
    }
}

//pk_test_UCJ2MxfKldq5aW6wn788nMan00QrabCR6d
//sk_test_uK2XwHhWYAhvR1Oh6QN0gmww00vlzitu5m