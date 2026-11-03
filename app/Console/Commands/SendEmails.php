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
    protected $signature = 'send:emails {--order= : Only process this order and skip password resets}';

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

        $failed = false;
        $orderId = $this->option('order');
        if ($orderId !== null && (! ctype_digit((string) $orderId) || (int) $orderId < 1)) {
            $this->error('The order ID must be a positive integer.');
            return self::FAILURE;
        }

        // A single-order test must never process unrelated password resets.
        if ($orderId === null) {
            $users = User::whereNotNull('password_reset_token')
                ->whereNotNull('password_reset_token_created_at')->limit(100)->get();
            foreach ($users as $user) {
                try {
                    Mail::to($this->recipient($user->email))->send(new PasswordResetLink($user));
                    User::where('id', $user->id)->where('password_reset_token', $user->password_reset_token)
                        ->update(['password_reset_token_created_at' => null]);
                } catch (\Throwable $error) {
                    $failed = true;
                    $this->deliveryFailed('password_reset', $user->id, $error);
                }
            }
        }

        $orders = Order::where('status', 'confirmed')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('order_payments')
                    ->whereColumn('order_payments.order_id', 'orders.id')
                    ->whereNull('order_payments.deleted_at')->where('payment_confirmed', 1);
            })
            ->where(function ($query) {
                $query->whereNull('email_confirmation_sent_at')->orWhereNull('email_notification_sent_at');
            });
        if ($orderId !== null) {
            $orders->where('orders.id', (int) $orderId);
        }
        foreach ($orders->orderBy('created_at')->limit(6)->get() as $order) {
            try {
                $details = $this->orderDetails($order->id);
                if (! $order->email_notification_sent_at) {
                    Mail::to($this->recipient(config('mail.order_notification_address')))->send(new OrderNotification($details));
                    $order->update(['email_notification_sent_at' => now()]);
                }
            } catch (\Throwable $error) {
                $failed = true;
                $this->deliveryFailed('order_notification', $order->id, $error);
            }
            try {
                if (! $order->email_confirmation_sent_at) {
                    $details = $this->orderDetails($order->id);
                    Mail::to($this->recipient($order->email))->send(new OrderConfirmation($details));
                    $order->update(['email_confirmation_sent_at' => now()]);
                }
            } catch (\Throwable $error) {
                $failed = true;
                $this->deliveryFailed('order_confirmation', $order->id, $error);
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function recipient(string $address): string
    {
        return config('mail.test_recipient') ?: $address;
    }

    private function deliveryFailed(string $type, int $id, \Throwable $error): void
    {
        // Transport exceptions may include private SMTP or message details.
        \Illuminate\Support\Facades\Log::warning('Email delivery failed; message remains pending.', [
            'type' => $type, 'record_id' => $id, 'exception' => get_class($error),
        ]);
        $this->error('Email delivery failed; the message remains pending for retry.');
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
