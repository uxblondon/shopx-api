<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use DB;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders = Order::join('order_payments', 'order_payments.order_id', 'orders.id')
        ->join('order_items', 'order_items.order_id', 'orders.id')
        ->select(['orders.id', 'orders.name', 'orders.email', DB::raw('count(order_items.id) as no_of_items'), 'orders.created_at', 'order_payments.payment_type', 'order_payments.amount'])
        ->orderBy('orders.created_at', 'desc')
        ->groupBy('orders.id')
        ->groupBy('order_payments.id')
        ->get();

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function ref()
    {
        
            $no_of_orders = Order::where('created_at', '>=', date('Y-m-d').' 00:00:00')->where('created_at', '<=', date('Y-m-d').' 59:59:59')->count();
            $sequence = date('ymd').str_pad($no_of_orders+1, 5, '0', STR_PAD_LEFT);
      
       
        return $sequence;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $customer = $request->get('customer');
        $order = array(
            'ref' => $this->ref(),
            'name' => $customer['name'],
        );

        return $order;
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
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
