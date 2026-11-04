<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CheckoutAttempt
{
    public static function token(Request $request): ?string
    {
        $request->validate(['checkout_token' => ['sometimes', 'required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        return $request->input('checkout_token');
    }

    public static function authorize(Request $request, Order $order): void
    {
        if ($order->checkout_token_hash) {
            $token = self::token($request);
            abort_unless($token && hash_equals($order->checkout_token_hash, hash('sha256', $token)), 404, 'Checkout not found.');
        }
    }

    public static function reserve(string $tokenHash): void
    {
        // Committed before the order transaction so create/cancel can lock the same durable row.
        DB::table('checkout_attempts')->insertOrIgnore([
            'token_hash' => $tokenHash, 'state' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function fingerprint(Request $request): string
    {
        return hash('sha256', json_encode(Arr::sortRecursive($request->only(['customer', 'basket_items', 'shipping', 'payment'])), JSON_THROW_ON_ERROR));
    }

    public static function replay(Order $order, string $fingerprint)
    {
        abort_unless(hash_equals((string) $order->checkout_payload_hash, $fingerprint), 409, 'This checkout already belongs to different basket details. Recover it before starting another.');
        return response()->json(['status' => 'success', 'data' => $order, 'message' => 'Existing checkout recovered.']);
    }
}
