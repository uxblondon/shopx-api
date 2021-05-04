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
use App\Models\OrderBilling;

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

        $last_order = Order::orderBy('id', 'desc')->first(['id']);

        $order_no = 1;
        if (isset($last_order->id)) {
            $order_no = $last_order->id;
        }

        $sequence = 'TH' . date('ymd') . str_pad($order_no, 6, '0', STR_PAD_LEFT) . strtoupper($shipping_method[0]) . $no_of_items;

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
                'email' => $customer['email'],
            );

            if ($payment['type'] === 'paypal') {
                $order_data['status'] = 'confirmed';
            }

            $order = Order::create($order_data);

            foreach ($basket_items as $item) {
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
            if ($shipping['method'] === 'delivery') {

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

                foreach ($deliveries as $delivery) {

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
                    foreach ($items as $item) {
                        $order_item = OrderItem::where('order_id', $order->id)
                            ->where('product_id', $item['product_id'])
                            ->where('variant_id', $item['id'])
                            ->first();

                        if (!$order_item) {
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
            } elseif ($shipping['method'] === 'collection') {

                $collection_option = $shipping['collection_option'];
                $order_collection_data = array(
                    'order_id' => $order->id,
                    'method' => 'collection',
                    'provider' => 'Trinity House',
                    'service' => 'Order Collection',
                    'speed' => $collection_option['speed'],
                    'cost' => $collection_option['cost'],
                );


                $order_delivery = OrderDelivery::create($order_collection_data);

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
                $collection_address_data['order_delivery_id'] = $order_delivery->id;
                OrderAddress::create($collection_address_data);
            }

            // payment 
            $billing = $payment['billing'];
            $billing_data = array(
                'order_id' => $order->id,
                'name' => $billing['name'],
                'address_line_1' => $billing['address_line_1'],
                'address_line_2' => $billing['address_line_2'],
                'city' => $billing['city'],
                'county' => $billing['county'],
                'postcode' => $billing['postcode'],
                'country_code' => $billing['country'],
                'email' => isset($billing['email']) ? $billing['email'] : '',
            );
            OrderBilling::create($billing_data);

            $order_payment_data = array(
                'order_id' => $order->id,
                'payment_type' => $payment['type'],
                'amount' => $payment['amount'],
                'payment_id' => isset($payment['payment_id']) ? $payment['payment_id'] : '',
                'payment_status' => isset($payment['payment_status']) ? $payment['payment_status'] : '',
            );
            OrderPayment::create($order_payment_data);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to save the order.' . $e->getMessage()]);
        }

        DB::commit();
        return response()->json(['status' => 'success', 'request' => $request->all(), 'data' => $order, 'message' => 'Order created successfully.']);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($order_id)
    {
        $order = Order::find($order_id);
        if ($order) {
            $order->items = OrderItem::where('order_id', $order->id)->get();

            $deliveries = OrderDelivery::where('order_id', $order->id)->get();

            $order_deliveries = [];
            foreach ($deliveries as $delivery) {
                if ($delivery->method === 'delivery') {

                    $delivery->address = OrderAddress::where('order_id', $order->id)
                        ->where('order_delivery_id', $delivery->id)
                        ->where('type', 'delivery')
                        ->first();

                    $delivery->items = OrderDeliveryItem::join('order_items', 'order_items.id', 'order_delivery_items.order_item_id')
                        ->where('order_delivery_items.order_delivery_id', $delivery->id)
                        ->get();
                } elseif ($delivery->method === 'collection') {

                    $delivery->address = OrderAddress::where('order_id', $order->id)
                        ->where('order_delivery_id', $delivery->id)
                        ->where('type', 'collection')
                        ->first();

                    $delivery->items = $order->items;
                }

                $order_deliveries[] = $delivery;
            }
            $order->deliveries = $order_deliveries;

            $payment = OrderPayment::where('order_id', $order->id)->first();
            if ($payment) {
                $payment->billing_details = OrderAddress::where('order_id', $order->id)
                    ->where('type', 'billing')
                    ->first();
            }
            $order->payment = $payment;
            return response()->json(['status' => 'success', 'data' => $order]);
        }
        return response()->json(['status' => 'error', 'message' => 'Order not found.']);
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

    public function removePending($order_id, $order_ref)
    {
        return Order::where('orders.status', 'pending')
            ->join('order_payments', 'orders.id', 'order_payments.order_id')
            ->where('orders.id', $order_id)
            ->where('orders.ref', $order_ref)
            ->whereNull('order_payments.payment_id')
            ->whereNull('order_payments.payment_status')
            ->forceDelete();
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
