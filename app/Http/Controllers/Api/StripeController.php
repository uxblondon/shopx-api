<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function paymentIntend(Request $request)
    {
        $request->validate(['order_id' => 'required|integer', 'order_ref' => 'required|string']);
        $order = Order::find($request->integer('order_id'));
        if (! $order || ! hash_equals((string) $order->ref, $request->string('order_ref')->toString())) {
            return response()->json(['status' => 'error', 'message' => 'Order not found.'], 404);
        }
        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $intent = DB::transaction(function () use ($order) {
                $payment = OrderPayment::where('order_id', $order->id)->where('payment_type', 'stripe')->lockForUpdate()->first();
                if (! $payment || $payment->payment_confirmed || $order->status !== 'pending') {
                    throw new \DomainException('This order is not awaiting a card payment.');
                }
                $amount = $this->amount($payment);
                if ($payment->payment_id) {
                    $intent = PaymentIntent::retrieve($payment->payment_id);
                    $this->validateIntent($intent, $payment, $order);
                } else {
                    $intent = PaymentIntent::create([
                        'description' => $order->ref,
                        'amount' => $amount,
                        'currency' => 'gbp',
                        'payment_method_types' => ['card'],
                        'metadata' => ['order_id' => (string) $order->id, 'order_ref' => $order->ref],
                    ], ['idempotency_key' => 'order-'.$order->id.'-'.hash('sha256', $order->ref.':'.$amount)]);
                    $payment->payment_id = $intent->id;
                    $this->validateIntent($intent, $payment, $order);
                }
                $payment->payment_status = $intent->status;
                $payment->save();
                return $intent;
            });
            return response()->json(['status' => 'success', 'secret' => $intent->client_secret]);
        } catch (\DomainException $error) {
            return response()->json(['status' => 'error', 'message' => $error->getMessage()], 422);
        } catch (\Exception $error) {
            return response()->json(['status' => 'error', 'message' => 'Unable to initialise card payment. Please try again.'], 503);
        }
    }

    public function payment(Request $request, $order_id)
    {
        $request->validate(['payment_id' => ['required', 'string', 'regex:/^pi_[A-Za-z0-9]+$/']]);
        $payment = OrderPayment::where('order_id', $order_id)->where('payment_type', 'stripe')->first();
        if (! $payment || ! Order::find($order_id) || ! $payment->payment_id || ! hash_equals($payment->payment_id, $request->input('payment_id'))) {
            return response()->json(['status' => 'error', 'message' => 'Payment does not belong to this order.'], 422);
        }
        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $intent = PaymentIntent::retrieve($payment->payment_id);
            $this->applyIntent($intent);
            if ($intent->status !== 'succeeded') {
                return response()->json(['status' => 'error', 'payment_status' => $intent->status,
                    'message' => 'Stripe has not confirmed this payment.'], 409);
            }
            return response()->json(['status' => 'success', 'payment_status' => 'succeeded', 'message' => 'Payment verified.']);
        } catch (\DomainException $error) {
            return response()->json(['status' => 'error', 'message' => $error->getMessage()], 422);
        } catch (\Exception $error) {
            return response()->json(['status' => 'error', 'message' => 'Payment verification is temporarily unavailable.'], 503);
        }
    }

    public function paymentStatus(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            return response()->json(['status' => 'error', 'message' => 'Webhook not configured.'], 503);
        }
        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), $secret);
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $error) {
            return response()->json(['status' => 'error', 'message' => 'Invalid webhook signature.'], 400);
        }
        if (! in_array($event->type, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.processing', 'payment_intent.canceled'], true)) {
            return response()->json(['status' => 'ignored']);
        }
        try {
            $this->applyIntent($event->data->object);
            return response()->json(['status' => 'success']);
        } catch (\DomainException $error) {
            return response()->json(['status' => 'error', 'message' => 'Webhook payment did not match the saved order.'], 422);
        } catch (\Exception $error) {
            return response()->json(['status' => 'error', 'message' => 'Webhook processing temporarily unavailable.'], 503);
        }
    }

    private function amount(OrderPayment $payment): int
    {
        $amount = (int) round((float) $payment->amount * 100);
        if ($amount < 50) {
            throw new \DomainException('Invalid card payment amount.');
        }
        return $amount;
    }

    private function validateIntent($intent, OrderPayment $payment, Order $order): void
    {
        if ($intent->id !== $payment->payment_id || (string) ($intent->metadata->order_id ?? '') !== (string) $order->id
            || (string) ($intent->metadata->order_ref ?? '') !== (string) $order->ref
            || $intent->currency !== 'gbp' || (int) $intent->amount !== $this->amount($payment)) {
            throw new \DomainException('Stripe payment does not match this order.');
        }
        if (str_starts_with((string) config('services.stripe.secret'), 'sk_test_') && $intent->livemode !== false) {
            throw new \DomainException('Expected a sandbox payment.');
        }
        if ($intent->status === 'succeeded' && (int) $intent->amount_received !== $this->amount($payment)) {
            throw new \DomainException('Stripe payment amount was not received in full.');
        }
    }

    private function applyIntent($intent): void
    {
        DB::transaction(function () use ($intent) {
            $payment = OrderPayment::where('payment_type', 'stripe')->where('payment_id', $intent->id)->lockForUpdate()->first();
            // The same Stripe account may send events for other applications.
            if (! $payment) {
                return;
            }
            $order = Order::find($payment->order_id);
            if (! $order) {
                return;
            }
            $this->validateIntent($intent, $payment, $order);
            if ($payment->payment_confirmed) {
                return; // Duplicate or older events must not downgrade a paid order.
            }
            $payment->payment_status = $intent->status;
            if ($intent->status === 'succeeded') {
                $payment->payment_confirmed = true;
                if ($order->status === 'pending') {
                    $order->update(['status' => 'confirmed']);
                }
            }
            $payment->save();
        });
    }
}
