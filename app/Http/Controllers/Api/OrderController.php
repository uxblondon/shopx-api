<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use DB;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryItem;
use App\Models\OrderAddress;
use App\Models\OrderPayment;

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
    public function ref($shipping_method, $no_of_items)
    {
        
            $no_of_orders = Order::where('created_at', '>=', date('Y-m-d').' 00:00:00')->where('created_at', '<=', date('Y-m-d').' 59:59:59')->count();
            $sequence = 'TH'.date('ymd').str_pad($no_of_orders+1, 5, '0', STR_PAD_LEFT).strtoupper($shipping_method[0]).$no_of_items;
      
       
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
        DB::beginTransaction();
        try {

            $customer = $request->get('customer');
        $basket_items = $request->get('basket_items');
        $shipping = $request->get('shipping');
        $payment = $request->get('payment');

        $order_data = array(
            'ref' => $this->ref($shipping['method'], count($basket_items)),
            'name' => $customer['name'],
            'email' => $customer['email']
        );

        $order = Order::create($order_data);

        $order_items = [];
        foreach($basket_items as $item) {
            $order_item = array(
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['id'],
                'title' => $item['title'],
                'width' => $item['width'],
                'length' => $item['length'],
                'height' => $item['height'],
                'weight' => $item['weight'],
                'separated_shipping_required' => $item['separated_shipping_required'],
                'additional_shipping_cost' => $item['additional_shipping_cost'],
                'variant_1_name' => $item['variant_1_name'],
                'variant_1_value' => $item['variant_1_value'],
                'variant_2_name' => $item['variant_2_name'],
                'variant_2_value' => $item['variant_2_value'],
                'variant_3_name' => $item['variant_3_name'],
                'variant_3_value' => $item['variant_3_value'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
            );

            OrderItem::create($order_item);
        }

        // shipping 

        if($shipping['method'] === 'delivery') {

            $delivery_address = $shipping['delivery_address'];

            $order_address_data = array(
                'order_id' => $order->id,
                'type' => 'delivery',
                'name' => $delivery_address['name'],
                'address_line_1' => $delivery_address['address_line_1'],
                'address_line_2' => $delivery_address['address_line_2'],
                'city' => $delivery_address['city'],
                'county' => $delivery_address['county'],
                'postcode' => $delivery_address['postcode'],
                'country_code' => $delivery_address['country'],
            );

            $deliveries = $shipping['deliveries'];

            foreach($deliveries as $delivery) {

                $delivery_option = $delivery['option'];

                $order_delivery_data = array(
                    'order_id' => $order->id,
                    'method' => 'delivery',
                    'provider' => $delivery_option['provider'],
                    'service' => $delivery_option['service'],
                    'speed' => $delivery_option['speed'],
                    'cost' => $delivery_option['cost'],
                );

                $order_delivery = OrderDelivery::create($order_delivery_data);

                $order_address_data['order_delivery_id'] = $order_delivery->id;
                OrderAddress::create($order_address_data);
                
                $items = $delivery['items'];
                foreach($items as $item) {
                    //find order item id 
                    // order_id variant_id product_id 
                    $order_item = OrderItem::where('order_id', $order->id)
                    ->where('product_id', $item['product_id'])
                    ->where('variant_id', $item['id'])
                    ->first();

                    if(!$order_item) {
                        DB::rollBack();
                        return response()->json(['status' => 'error', 'message' => 'Delivery item not found in order items.']);
                    } 

                    $delivery_item_data = array(
                        'order_delivery_id' => $order_delivery->id,
                        'order_item_id' => $order_item->id
                    );

                    OrderDeliveryItem::create($delivery_item_data);
                }
            }


        } elseif($shipping['method'] === 'collection') {

            $collection_option = $shipping['collection_option'];
            $order_collection_data = array(
                'order_id' => $order->id,
                'method' => 'collection',
                'provider' => 'Trinity House',
                'service' => 'Order Collection',
                'speed' => $collection_option['speed'],
                'cost' => $collection_option['cost'],
            );
            OrderDelivery::create($order_collection_data);

            $collection_address = $shipping['collection_address'];
            $collection_address_data = array(
                'order_id' => $order->id,
                'type' => 'collection',
                'name' => $collection_address['name'],
                'address_line_1' => $collection_address['address_line_1'],
                'address_line_2' => $collection_address['address_line_2'],
                'city' => $collection_address['city'],
                'county' => $collection_address['county'],
                'postcode' => $collection_address['postcode'],
                'country_code' => $collection_address['country_code'],
            );
            OrderAddress::create($collection_address_data);
        }

        // payment 
        $order_payment_data = array(
            'order_id' => $order->id,
            'payment_type' => $payment['type'],
            'amount' => $payment['amount'],
        );
        OrderPayment::create($order_payment_data);

        } catch(\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to save the order.'.$e->getMessage()]);
        }

        DB::commit();
        return response()->json(['status' => 'success', 'request' => $request->all(), 'data' => $order, 'message' => 'Order created successfully.']);

       // return $request->all();
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
