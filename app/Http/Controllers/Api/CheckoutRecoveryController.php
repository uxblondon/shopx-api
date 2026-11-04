<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutRecoveryController extends Controller
{
    public function recover(Request $request)
    {
        return $this->operate($request, false);
    }

    public function cancel(Request $request)
    {
        return $this->operate($request, true);
    }

    private function operate(Request $request, bool $cancel)
    {
        $request->validate(['checkout_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $tokenHash = hash('sha256', $request->input('checkout_token'));
        if (! $cancel && ! Order::withTrashed()->where('checkout_token_hash', $tokenHash)->exists()) {
            return response()->json(['status' => 'error', 'code' => 'CHECKOUT_NOT_FOUND', 'message' => 'Saved checkout not found.'], 404);
        }
        CheckoutAttempt::reserve($tokenHash);
        try {
            return DB::transaction(function () use ($tokenHash, $cancel) {
                DB::table('checkout_attempts')->where('token_hash', $tokenHash)->lockForUpdate()->firstOrFail();
                $order = Order::withTrashed()->where('checkout_token_hash', $tokenHash)->first();
                if (! $order) {
                    if (! $cancel) {
                        return response()->json(['status' => 'error', 'code' => 'CHECKOUT_NOT_FOUND', 'message' => 'Saved checkout not found.'], 404);
                    }
                    DB::table('checkout_attempts')->where('token_hash', $tokenHash)->update(['state' => 'cancelled', 'updated_at' => now()]);
                    return response()->json(['status' => 'success', 'data' => [
                        'state' => 'cancelled', 'id' => null, 'ref' => '', 'method' => null, 'amount' => 0, 'items' => [],
                    ]])->header('Cache-Control', 'no-store');
                }
                $payment = OrderPayment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
                $order->refresh();
                $provider = null;
                if (! $payment->payment_confirmed && $order->status !== 'cancelled') {
                    if ($payment->payment_type === 'stripe') {
                        app(StripeController::class)->synchronize($order);
                    } elseif ($payment->payment_type === 'paypal') {
                        $provider = app(PaypalController::class)->synchronize($order);
                    } else {
                        throw new \DomainException('This checkout uses an unsupported payment method.');
                    }
                    $payment->refresh();
                    $order->refresh();
                }
                if (! $payment->payment_confirmed && in_array($payment->payment_status, ['canceled', 'VOIDED'], true) && $order->status === 'pending') {
                    $order->update(['status' => 'cancelled']);
                }
                if ($cancel && ! $payment->payment_confirmed && ! in_array($order->status, ['pending', 'cancelled'], true)) {
                    throw new \DomainException('This order cannot be cancelled here. Please contact the shop.');
                }
                if ($cancel && ! $payment->payment_confirmed && $order->status !== 'cancelled') {
                    if ($payment->payment_type === 'stripe') {
                        app(StripeController::class)->cancelSaved($order);
                    } elseif ($payment->payment_type === 'paypal') {
                        app(PaypalController::class)->assertCancellable($provider);
                    }
                    $payment->refresh();
                    if (! $payment->payment_confirmed) {
                        // PayPal approval alone cannot charge; capture rechecks this state under the same payment lock.
                        $order->update(['status' => 'cancelled']);
                        DB::table('checkout_attempts')->where('token_hash', $tokenHash)->update(['state' => 'cancelled', 'updated_at' => now()]);
                    }
                }
                $payment->refresh();
                $order->refresh();
                return response()->json(['status' => 'success', 'data' => [
                    'id' => $order->id,
                    'ref' => $order->ref,
                    'method' => $payment->payment_type,
                    'amount' => $payment->amount,
                    'state' => $payment->payment_confirmed ? 'paid' : ($order->status === 'cancelled' ? 'cancelled' : (($order->status !== 'pending' || $order->trashed()) ? 'closed' : 'pending')),
                    'provider_status' => $payment->payment_status,
                    'paypal_order_id' => $payment->payment_type === 'paypal' ? $payment->paypal_order_id : null,
                    'items' => OrderItem::where('order_id', $order->id)->get(['title', 'quantity', 'price']),
                ]])->header('Cache-Control', 'no-store');
            });
        } catch (\DomainException $error) {
            return response()->json(['status' => 'error', 'message' => $error->getMessage()], 409);
        } catch (\Throwable $error) {
            return response()->json(['status' => 'error', 'message' => 'Payment status is temporarily unavailable. Your saved checkout is retained; check again before paying.'], 503);
        }
    }
}
