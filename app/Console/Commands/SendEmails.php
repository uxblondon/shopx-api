<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryItem;
use App\Models\OrderAddress;
use App\Models\OrderPayment;
use App\Models\OrderBilling;

use App\Mail\OrderConfirmation;
use App\Mail\OrderNotification;
use App\Mail\PasswordResetLink;
use Illuminate\Support\Facades\Mail;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends emails';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

         // send user password reset emails 
         $users = User::whereNotNull('password_reset_token')
         ->whereNotNull('password_reset_token_created_at')
         ->get();

         if ($users->count() > 0) {
             foreach ($users as $user) {
                 try {
                     Mail::to($user->email)->send(new PasswordResetLink($user));
                 } catch (\Exception $e) {
                 }
                 User::where('id', $user->id)
                 ->update(['password_reset_token_created_at' => NULL]);
             }
         }

        // send orders email
        $orders = Order::where('status', 'confirmed')
            ->where(function ($query) {
                $query->whereNull('email_confirmation_sent_at')->orWhereNull('email_notification_sent_at');
            })
            ->orderBy('created_at')
            ->limit(6)
            ->get();

        if ($orders->count() > 0) {
            foreach ($orders as $order) {
                // send notification
                if ($order->email_notification_sent_at == '') {
                    try {
                        $order_details = $this->orderDetails($order->id);
                        Mail::to($order->email)->send(new OrderNotification($order_details));
                    } catch (\Exception $e) {
                    }
                    Order::where('id', $order->id)->update(['email_notification_sent_at' => date('Y-m-d H:i:s')]);
                }

                // send confirmation 
                if ($order->email_confirmation_sent_at == '') {
                    try {
                        $order_details = $this->orderDetails($order->id);
                        Mail::to($order->email)->send(new OrderConfirmation($order_details));
                    } catch (\Exception $e) {
                    }
                    Order::where('id', $order->id)->update(['email_confirmation_sent_at' => date('Y-m-d H:i:s')]);
                }

                sleep(2);
            }

            sleep(5);
        }

       
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function orderDetails($order_id)
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
                $payment->billing_details = OrderBilling::where('order_id', $order->id)->first();
            }
            $order->payment = $payment;

            return $order;
        }
        return false;
    }
}
