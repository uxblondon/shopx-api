<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\CheckoutMoney;
use App\Services\PaypalGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaypalController extends Controller
{
    public function __construct(private PaypalGateway $gateway) {}

    public function create(Request $request)
    {
        if (! $this->gateway->ready()) {
            return $this->unavailable();
        }
        $request->validate(['order_id' => 'required|integer', 'order_ref' => 'required|string']);
        $order = Order::find($request->integer('order_id'));
        if (! $order || ! hash_equals((string) $order->ref, $request->input('order_ref'))) {
            return response()->json(['status' => 'error', 'message' => 'Order not found.'], 404);
        }
        try {
            // Save a durable request ID before making the remote call, including timeout retries.
            DB::transaction(function () use ($order) {
                $payment = $this->payment($order->id)->lockForUpdate()->first();
                if (! $payment || $payment->payment_confirmed || $order->status !== 'pending') {
                    throw new \DomainException('This order is not awaiting PayPal payment.');
                }
                if (! $payment->paypal_request_id) {
                    $payment->update(['paypal_request_id' => (string) Str::uuid()]);
                }
            });
            $id = DB::transaction(function () use ($order) {
                $payment = $this->payment($order->id)->lockForUpdate()->firstOrFail();
                if ($payment->payment_confirmed) {
                    throw new \DomainException('This order is already paid.');
                }
                if ($payment->paypal_order_id) {
                    return $payment->paypal_order_id;
                }
                $provider = $this->gateway->request('POST', '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => (string) $order->id,
                        'custom_id' => $payment->paypal_request_id,
                        'description' => $order->ref,
                        'payee' => ['merchant_id' => config('services.paypal.merchant_id')],
                        'amount' => ['currency_code' => 'GBP', 'value' => $this->amount($payment)],
                    ]],
                    'application_context' => ['shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW'],
                ], $payment->paypal_request_id);
                if (! preg_match('/^[A-Z0-9]{10,64}$/', $provider['id'] ?? '')) {
                    throw new \RuntimeException('Invalid PayPal order response.');
                }
                $payment->paypal_order_id = $provider['id'];
                $this->validateOrder($provider, $payment, $order);
                $payment->payment_status = 'CREATED';
                $payment->save();
                return $payment->paypal_order_id;
            });
            return response()->json(['status' => 'success', 'paypal_order_id' => $id]);
        } catch (\DomainException $error) {
            return response()->json(['status' => 'error', 'message' => $error->getMessage()], 422);
        } catch (\Throwable $error) {
            return $this->unavailable();
        }
    }

    public function capture(Request $request, int $order_id)
    {
        if (! $this->gateway->ready()) {
            return $this->unavailable();
        }
        $request->validate(['order_ref' => 'required|string', 'paypal_order_id' => ['required', 'string', 'regex:/^[A-Z0-9]{10,64}$/']]);
        $order = Order::find($order_id);
        $payment = $this->payment($order_id)->first();
        if (! $order || ! $payment || ! hash_equals((string) $order->ref, $request->input('order_ref'))
            || ! $payment->paypal_order_id || ! hash_equals($payment->paypal_order_id, $request->input('paypal_order_id'))) {
            return response()->json(['status' => 'error', 'message' => 'PayPal payment does not belong to this order.'], 422);
        }
        if ($order->status !== 'pending' && ! $payment->payment_confirmed) {
            return response()->json(['status' => 'error', 'message' => 'This order is not awaiting payment.'], 422);
        }
        try {
            $path = '/v2/checkout/orders/'.$payment->paypal_order_id;
            $provider = $this->gateway->request('GET', $path);
            $this->validateOrder($provider, $payment, $order);
            if (($provider['status'] ?? '') === 'APPROVED') {
                try {
                    $this->gateway->request('POST', $path.'/capture', null,
                        substr(hash('sha256', $payment->paypal_request_id.':capture'), 0, 32));
                } catch (\Throwable $error) {
                    // A timeout can happen after capture. Do not start a different payment.
                }
                // Capture responses may omit order metadata; verify the full saved provider order.
                $provider = $this->gateway->request('GET', $path);
            }
            $paid = $this->apply($provider);
            if (! $paid) {
                return response()->json(['status' => 'error', 'message' => 'PayPal has not confirmed payment. Your basket is retained.',
                    'code' => 'PAYPAL_NOT_COMPLETED'], 409);
            }
            return response()->json(['status' => 'success', 'payment_status' => 'COMPLETED']);
        } catch (\DomainException $error) {
            return response()->json(['status' => 'error', 'message' => $error->getMessage()], 422);
        } catch (\Throwable $error) {
            return $this->unavailable();
        }
    }

    public function paymentStatus(Request $request)
    {
        if (! $this->gateway->ready() || ! config('services.paypal.webhook_id')) {
            return $this->unavailable();
        }
        $event = json_decode($request->getContent(), true);
        if (! is_array($event) || ! isset($event['id'], $event['event_type'], $event['resource'])) {
            return response()->json(['status' => 'error'], 400);
        }
        $headers = [];
        foreach (['auth-algo', 'cert-url', 'transmission-id', 'transmission-sig', 'transmission-time'] as $header) {
            if (! $request->header('paypal-'.$header)) {
                return response()->json(['status' => 'error', 'message' => 'Missing PayPal signature.'], 400);
            }
            $headers[str_replace('-', '_', $header)] = $request->header('paypal-'.$header);
        }
        try {
            $verified = $this->gateway->request('POST', '/v1/notifications/verify-webhook-signature', $headers + [
                'webhook_id' => config('services.paypal.webhook_id'), 'webhook_event' => $event,
            ]);
            if (($verified['verification_status'] ?? '') !== 'SUCCESS') {
                return response()->json(['status' => 'error', 'message' => 'Invalid PayPal signature.'], 400);
            }
            if (! in_array($event['event_type'], ['PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.PENDING', 'PAYMENT.CAPTURE.DENIED'], true)) {
                return response()->json(['status' => 'ignored']);
            }
            $id = $event['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
            if (! preg_match('/^[A-Z0-9]{10,64}$/', $id) || ! OrderPayment::where('payment_type', 'paypal')->where('paypal_order_id', $id)->exists()) {
                return response()->json(['status' => 'ignored']);
            }
            $this->apply($this->gateway->request('GET', '/v2/checkout/orders/'.$id));
            return response()->json(['status' => 'success']);
        } catch (\DomainException $error) {
            return response()->json(['status' => 'error', 'message' => 'PayPal payment does not match the order.'], 422);
        } catch (\Throwable $error) {
            return $this->unavailable();
        }
    }

    private function payment(int $id)
    {
        return OrderPayment::where('order_id', $id)->where('payment_type', 'paypal');
    }

    private function amount(OrderPayment $payment): string
    {
        return CheckoutMoney::pounds(CheckoutMoney::pence($payment->amount));
    }

    private function validateOrder(array $provider, OrderPayment $payment, Order $order): array
    {
        $units = $provider['purchase_units'] ?? [];
        $unit = $units[0] ?? [];
        if (($provider['id'] ?? '') !== $payment->paypal_order_id || ($provider['intent'] ?? '') !== 'CAPTURE' || count($units) !== 1
            || ($unit['reference_id'] ?? '') !== (string) $order->id || ($unit['custom_id'] ?? '') !== $payment->paypal_request_id
            || ($unit['payee']['merchant_id'] ?? '') !== config('services.paypal.merchant_id')
            || ($unit['amount']['currency_code'] ?? '') !== 'GBP' || ($unit['amount']['value'] ?? '') !== $this->amount($payment)) {
            throw new \DomainException('PayPal payment does not match this order.');
        }
        return $unit;
    }

    private function apply(array $provider): bool
    {
        return DB::transaction(function () use ($provider) {
            $payment = OrderPayment::where('payment_type', 'paypal')->where('paypal_order_id', $provider['id'] ?? '')->lockForUpdate()->first();
            $order = $payment ? Order::find($payment->order_id) : null;
            if (! $order) {
                throw new \DomainException('PayPal order not found.');
            }
            $unit = $this->validateOrder($provider, $payment, $order);
            $captures = $unit['payments']['captures'] ?? [];
            if (count($captures) > 1) {
                throw new \DomainException('Unexpected PayPal captures.');
            }
            $capture = $captures[0] ?? null;
            if ($capture && (($capture['amount']['currency_code'] ?? '') !== 'GBP'
                || ($capture['amount']['value'] ?? '') !== $this->amount($payment)
                || ! preg_match('/^[A-Z0-9]{10,64}$/', $capture['id'] ?? ''))) {
                throw new \DomainException('PayPal capture amount does not match.');
            }
            $paid = ($provider['status'] ?? '') === 'COMPLETED' && ($capture['status'] ?? '') === 'COMPLETED'
                && ($capture['final_capture'] ?? false) === true;
            if ($payment->payment_confirmed) {
                return true;
            }
            $payment->payment_status = $capture['status'] ?? $provider['status'] ?? 'PENDING';
            if ($paid) {
                $payment->payment_id = $capture['id'];
                $payment->payment_confirmed = true;
                if ($order->status === 'pending') {
                    $order->update(['status' => 'confirmed']);
                }
            }
            $payment->save();
            return $paid;
        });
    }

    private function unavailable()
    {
        return response()->json(['status' => 'error', 'message' => 'PayPal is unavailable. Your basket is retained.'], 503);
    }
}
