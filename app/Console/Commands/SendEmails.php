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
    protected $signature = 'send:emails {--order= : Only process this order and skip password resets} {--scheduled : Use configured automation and backlog cutoff} {--dry-run : Count eligible messages without sending or marking them}';

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

    private ?string $automationMailer = null;

    public function handle()
    {
        $this->automationMailer = null;
        $since = null;
        $afterId = null;
        if ($this->option('scheduled')) {
            if (! config('mail.automation.enabled')) {
                $this->info('Automatic email is disabled.');
                return self::SUCCESS;
            }
            $since = config('mail.automation.since');
            $afterId = config('mail.automation.order_after_id');
            $mailer = config('mail.automation.mailer');
            try {
                $date = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d H:i:s', (string) $since, 'UTC');
                if (! $date || $date->format('Y-m-d H:i:s') !== $since || ! ctype_digit((string) $afterId)
                    || ! config('mail.mailers.'.$mailer)) {
                    throw new \RuntimeException('Invalid automation settings.');
                }
                if (app()->environment('staging') && (! filter_var(config('mail.test_recipient'), FILTER_VALIDATE_EMAIL)
                    || ! in_array($mailer, ['mailgun', 'array', 'log'], true))) {
                    throw new \RuntimeException('Staging automation requires a test recipient.');
                }
            } catch (\Throwable $error) {
                $this->error('Automatic email needs a valid UTC cutoff, order boundary, mailer and staging test recipient.');
                return self::FAILURE;
            }
            $this->automationMailer = $mailer;
        }
        $failed = false;
        $orderId = $this->option('order');
        if ($orderId !== null && (! ctype_digit((string) $orderId) || (int) $orderId < 1)) {
            $this->error('The order ID must be a positive integer.');
            return self::FAILURE;
        }

        // A single-order test must never process unrelated password resets.
        if ($orderId === null) {
            if (! $this->option('dry-run')) {
                User::where('password_reset_expires_at', '<=', now())->update([
                    'password_reset_token' => null, 'password_reset_payload' => null,
                    'password_reset_token_created_at' => null, 'password_reset_expires_at' => null,
                ]);
            }
            $users = User::whereNotNull('password_reset_token')
                ->whereNotNull('password_reset_token_created_at')
                ->whereNotNull('password_reset_payload')
                ->where('password_reset_expires_at', '>', now())
                ->where('disabled', 0)->whereNull('deleted_at');
            if ($since !== null) {
                $users->where('password_reset_token_created_at', '>=', $since);
            }
            $users = $users->orderBy('id')->limit(100)->get();
            if ($this->option('dry-run')) {
                $this->line('Eligible password-reset messages: '.$users->count());
            }
            foreach ($users as $user) {
                if ($this->option('dry-run')) {
                    continue;
                }
                try {
                    $this->sendMessage($user->email, new PasswordResetLink($user, \Illuminate\Support\Facades\Crypt::decryptString($user->password_reset_payload)));
                    User::where('id', $user->id)->where('password_reset_token', $user->password_reset_token)
                        ->update(['password_reset_token_created_at' => null, 'password_reset_payload' => null]);
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
        if ($since !== null) {
            $orders->where('orders.created_at', '>=', $since)->where('orders.id', '>', (int) $afterId);
        }
        if ($orderId !== null) {
            $orders->where('orders.id', (int) $orderId);
        }
        $orders = $orders->orderBy('created_at')->limit(6)->get();
        if ($this->option('dry-run')) {
            $this->line('Eligible order messages: '.$orders->sum(fn ($order) => (int) ! $order->email_notification_sent_at + (int) ! $order->email_confirmation_sent_at));
            return self::SUCCESS;
        }
        foreach ($orders as $order) {
            try {
                $details = $this->orderDetails($order->id);
                if (! $order->email_notification_sent_at) {
                    $this->sendMessage(config('mail.order_notification_address'), new OrderNotification($details));
                    $order->update(['email_notification_sent_at' => now()]);
                }
            } catch (\Throwable $error) {
                $failed = true;
                $this->deliveryFailed('order_notification', $order->id, $error);
            }
            try {
                if (! $order->email_confirmation_sent_at) {
                    $details = $this->orderDetails($order->id);
                    $this->sendMessage($order->email, new OrderConfirmation($details));
                    $order->update(['email_confirmation_sent_at' => now()]);
                }
            } catch (\Throwable $error) {
                $failed = true;
                $this->deliveryFailed('order_confirmation', $order->id, $error);
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function sendMessage(string $address, \Illuminate\Mail\Mailable $message): void
    {
        if ($this->automationMailer !== null) {
            Mail::mailer($this->automationMailer)->to($this->recipient($address))->send($message);
        } else {
            Mail::to($this->recipient($address))->send($message);
        }
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
