<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;

class PaypalPaymentTest extends TestCase
{
    use RefreshDatabase;
    private const BASE = 'https://api-m.sandbox.paypal.com';
    private const ID = 'PPORDERFIXTURE1234';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paypal.enabled' => true, 'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'sandbox-client', 'services.paypal.secret' => 'sandbox-secret',
            'services.paypal.merchant_id' => 'MERCHANTFIXTURE', 'services.paypal.webhook_id' => 'webhook-fixture']);
        Http::preventStrayRequests();
    }

    private function order(): Order
    {
        $order = Order::create(['ref' => 'PAYPAL-FIXTURE', 'name' => 'Sandbox', 'email' => 'paypal@example.test']);
        OrderPayment::create(['order_id' => $order->id, 'payment_type' => 'paypal', 'amount' => '10.00',
            'paypal_order_id' => self::ID, 'paypal_request_id' => '26cb87aa-e3d6-4e16-b69b-2e0de7c5e355', 'payment_status' => 'CREATED']);
        return $order;
    }

    private function provider(Order $order, string $status = 'COMPLETED', string $captureStatus = 'COMPLETED'): array
    {
        $payment = OrderPayment::where('order_id', $order->id)->first();
        $unit = ['reference_id' => (string) $order->id, 'custom_id' => $payment->paypal_request_id,
            'payee' => ['merchant_id' => 'MERCHANTFIXTURE'], 'amount' => ['currency_code' => 'GBP', 'value' => '10.00']];
        if ($status === 'COMPLETED') {
            $unit['payments']['captures'] = [['id' => 'PPCAPTUREFIXTURE1', 'status' => $captureStatus, 'final_capture' => true,
                'amount' => ['currency_code' => 'GBP', 'value' => '10.00']]];
        }
        return ['id' => self::ID, 'intent' => 'CAPTURE', 'status' => $status, 'purchase_units' => [$unit]];
    }

    private function fake(array $provider, ?array $captured = null): void
    {
        Http::fake([
            self::BASE.'/v1/oauth2/token' => Http::response(['access_token' => 'fixture-access-token']),
            self::BASE.'/v2/checkout/orders/'.self::ID => $captured ? Http::sequence()->push($provider)->push($captured) : Http::response($provider),
            self::BASE.'/v2/checkout/orders/'.self::ID.'/capture' => Http::response(['id' => self::ID, 'status' => 'COMPLETED']),
            self::BASE.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);
    }

    private function capture(Order $order)
    {
        return $this->postJson('/api/paypal/'.$order->id.'/capture', ['order_ref' => $order->ref, 'paypal_order_id' => self::ID]);
    }

    private function webhook(string $type = 'PAYMENT.CAPTURE.COMPLETED')
    {
        return $this->postJson('/api/paypal-payment-status', ['id' => 'EVTFIXTURE', 'event_type' => $type,
            'resource' => ['id' => 'PPCAPTUREFIXTURE1', 'supplementary_data' => ['related_ids' => ['order_id' => self::ID]]]],
            ['PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert',
                'PAYPAL-TRANSMISSION-ID' => 'transmission-fixture', 'PAYPAL-TRANSMISSION-SIG' => 'signature-fixture',
                'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String()]);
    }

    public function test_create_uses_saved_total_and_persists_provider_order_identity(): void
    {
        $order = $this->order();
        OrderPayment::where('order_id', $order->id)->update(['paypal_order_id' => null, 'paypal_request_id' => null]);
        $seen = [];
        Http::fake(function ($request) use ($order, &$seen) {
            if (str_ends_with($request->url(), '/oauth2/token')) return Http::response(['access_token' => 'fixture']);
            if ($request->method() === 'POST') $seen[] = $request;
            return Http::response($this->provider($order, 'CREATED'), 201);
        });
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/paypal/create-order', ['order_id' => $order->id, 'order_ref' => $order->ref, 'amount' => 1])
                ->assertOk()->assertJsonPath('paypal_order_id', self::ID);
        }
        $this->assertCount(1, $seen);
        $this->assertSame('10.00', $seen[0]['purchase_units'][0]['amount']['value']);
        $this->assertSame('MERCHANTFIXTURE', $seen[0]['purchase_units'][0]['payee']['merchant_id']);
        $this->assertNotEmpty($seen[0]->header('PayPal-Request-Id'));
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'paypal_order_id' => self::ID, 'payment_confirmed' => 0]);
    }

    public function test_create_timeout_reuses_the_durable_request_id(): void
    {
        $order = $this->order();
        OrderPayment::where('order_id', $order->id)->update(['paypal_order_id' => null, 'paypal_request_id' => null]);
        $seen = [];
        Http::fake(function ($request) use ($order, &$seen) {
            if (str_ends_with($request->url(), '/oauth2/token')) return Http::response(['access_token' => 'fixture']);
            $seen[] = $request->header('PayPal-Request-Id');
            if (count($seen) === 1) throw new ConnectionException('Simulated timeout');
            return Http::response($this->provider($order, 'CREATED'), 201);
        });
        $payload = ['order_id' => $order->id, 'order_ref' => $order->ref];
        $this->postJson('/api/paypal/create-order', $payload)->assertStatus(503);
        $this->postJson('/api/paypal/create-order', $payload)->assertOk();
        $this->assertSame($seen[0], $seen[1]);
    }

    public function test_approved_order_is_captured_by_server_and_confirmed_once(): void
    {
        $order = $this->order();
        $this->fake($this->provider($order, 'APPROVED'), $this->provider($order));
        $this->capture($order)->assertOk()->assertJsonPath('payment_status', 'COMPLETED');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 1, 'payment_id' => 'PPCAPTUREFIXTURE1']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/capture') && !empty($r->header('PayPal-Request-Id')));
    }

    public function test_retrieved_completed_payment_does_not_capture_again_or_downgrade_fulfilment(): void
    {
        $order = $this->order(); $this->fake($this->provider($order));
        $this->capture($order)->assertOk();
        $order->update(['status' => 'shipped']);
        $this->capture($order)->assertOk();
        $this->assertSame('shipped', $order->fresh()->status);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/capture'));
        $this->assertDatabaseCount('order_payments', 1);
    }

    public function test_capture_timeout_recovers_provider_completion_without_a_second_capture(): void
    {
        $order = $this->order(); $gets = 0; $posts = 0;
        Http::fake(function ($request) use ($order, &$gets, &$posts) {
            if (str_ends_with($request->url(), '/oauth2/token')) return Http::response(['access_token' => 'fixture']);
            if ($request->method() === 'POST') { $posts++; throw new ConnectionException('Timeout after provider capture'); }
            return Http::response($this->provider($order, ++$gets === 1 ? 'APPROVED' : 'COMPLETED'));
        });
        $this->capture($order)->assertOk();
        $this->assertSame(1, $posts);
        $this->assertSame(2, $gets);
    }

    public function test_other_provider_order_cannot_be_attached(): void
    {
        $order = $this->order();
        $this->postJson('/api/paypal/'.$order->id.'/capture', ['order_ref' => $order->ref, 'paypal_order_id' => 'OTHERORDERFIXTURE1'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_wrong_merchant_currency_amount_metadata_or_capture_amount_cannot_confirm(): void
    {
        foreach (['merchant', 'currency', 'amount', 'metadata', 'capture_amount', 'partial'] as $change) {
            $order = $this->order(); $provider = $this->provider($order);
            if ($change === 'merchant') $provider['purchase_units'][0]['payee']['merchant_id'] = 'OTHER';
            if ($change === 'currency') $provider['purchase_units'][0]['amount']['currency_code'] = 'USD';
            if ($change === 'amount') $provider['purchase_units'][0]['amount']['value'] = '1.00';
            if ($change === 'metadata') $provider['purchase_units'][0]['custom_id'] = 'OTHER';
            if ($change === 'capture_amount') $provider['purchase_units'][0]['payments']['captures'][0]['amount']['value'] = '1.00';
            if ($change === 'partial') $provider['purchase_units'][0]['payments']['captures'][] = $provider['purchase_units'][0]['payments']['captures'][0];
            $this->fake($provider); $this->capture($order)->assertStatus(422);
            $this->assertSame('pending', $order->fresh()->status);
            OrderPayment::where('order_id', $order->id)->forceDelete();
        }
    }

    public function test_unapproved_and_pending_captures_are_not_confirmed(): void
    {
        $order = $this->order(); $this->fake($this->provider($order, 'CREATED'));
        $this->capture($order)->assertStatus(409);
        $this->fake($this->provider($order, 'COMPLETED', 'PENDING'));
        $this->capture($order)->assertStatus(409);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 0]);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/capture'));
    }

    public function test_unsigned_and_invalid_webhooks_cannot_confirm_payment(): void
    {
        $order = $this->order();
        $this->postJson('/api/paypal-payment-status', ['id' => 'EVTFIXTURE', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []])->assertStatus(400);
        Http::assertNothingSent();
        Http::fake([self::BASE.'/v1/oauth2/token' => Http::response(['access_token' => 'fixture']),
            self::BASE.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE'])]);
        $this->webhook()->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_verified_webhook_retrieves_payment_and_duplicate_events_are_idempotent(): void
    {
        $order = $this->order(); $this->fake($this->provider($order));
        $this->webhook()->assertOk();
        $this->webhook('PAYMENT.CAPTURE.PENDING')->assertOk();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 1, 'payment_status' => 'COMPLETED']);
    }

    public function test_sandbox_app_refuses_live_mode_and_missing_webhook_configuration(): void
    {
        $order = $this->order(); config(['services.paypal.mode' => 'live']);
        $this->capture($order)->assertStatus(503); Http::assertNothingSent();
        config(['services.paypal.mode' => 'sandbox', 'services.paypal.webhook_id' => '']);
        $this->webhook()->assertStatus(503); Http::assertNothingSent();
    }

    public function test_new_paypal_order_ignores_claimed_capture_and_remains_pending(): void
    {
        $product = \App\Models\Product::create(['title' => 'Gift', 'slug' => 'gift', 'status' => 'published', 'description' => 'Gift', 'created_by' => 1]);
        $variant = \App\Models\ProductVariant::create(['product_id' => $product->id, 'price' => 10, 'width' => 1, 'length' => 1, 'height' => 1, 'created_by' => 1]);
        $point = \App\Models\CollectionPoint::create(['name' => 'Test collection', 'address_line_1' => '1 Test Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country_code' => 'GB', 'active' => 1, 'created_by' => 1]);
        $rate = \App\Models\CollectionRate::create(['collection_point_id' => $point->id, 'speed' => 'Test', 'cost' => 0, 'available' => 1, 'created_by' => 1]);
        $address = ['name' => 'Test', 'address_line_1' => '1 Test Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'];
        $r = $this->postJson('/api/orders', ['customer' => ['name' => 'Test', 'email' => 'paypal@example.test'], 'basket_items' => [['id' => $variant->id, 'quantity' => 1]],
            'shipping' => ['method' => 'collection', 'collection_address' => ['id' => $point->id], 'collection_option' => ['id' => $rate->id]],
            'payment' => ['type' => 'paypal', 'amount' => 10, 'billing' => $address, 'payment_id' => 'FORGEDCAPTURE', 'payment_status' => 'COMPLETED']])->assertOk();
        $id = $r->json('data.id');
        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $id, 'payment_id' => '', 'payment_status' => 'pending', 'payment_confirmed' => 0]);
    }
}
