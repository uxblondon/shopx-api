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