<?php

namespace Tests\Feature;

use App\Models\CollectionPoint;
use App\Models\CollectionRate;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PaypalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CheckoutRecoveryTest extends TestCase
{
    use RefreshDatabase;
    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(new \Stripe\HttpClient\CurlClient());
        parent::tearDown();
    }

    private function payload(): array
    {
        $product = Product::create(['title' => 'Gift', 'slug' => 'gift', 'status' => 'published', 'description' => 'Gift', 'created_by' => 1]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'price' => 10, 'width' => 1, 'length' => 1, 'height' => 1, 'created_by' => 1]);
        $point = CollectionPoint::create(['name' => 'Collection', 'address_line_1' => '1 Test Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country_code' => 'GB', 'active' => 1, 'created_by' => 1]);
        $rate = CollectionRate::create(['collection_point_id' => $point->id, 'speed' => 'Next day', 'cost' => 0, 'available' => 1, 'created_by' => 1]);
        $address = ['name' => 'Customer', 'address_line_1' => '1 Test Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'];
        return ['checkout_token' => self::TOKEN, 'customer' => ['name' => 'Customer', 'email' => 'customer@example.test'],
            'basket_items' => [['id' => $variant->id, 'quantity' => 1]],
            'shipping' => ['method' => 'collection', 'collection_option' => $rate->toArray(), 'collection_address' => $point->toArray()],
            'payment' => ['type' => 'stripe', 'amount' => 10, 'billing' => $address]];
    }

    private function order(string $method = 'stripe', string $providerId = ''): Order
    {
        $order = Order::create(['ref' => 'RECOVERY-FIXTURE', 'name' => 'Customer', 'email' => 'private@example.test', 'checkout_token_hash' => hash('sha256', self::TOKEN)]);
        OrderPayment::create(['order_id' => $order->id, 'payment_type' => $method, 'amount' => '10.00',
            'payment_id' => $method === 'stripe' ? $providerId : '', 'paypal_order_id' => $method === 'paypal' ? $providerId : null,
            'paypal_request_id' => '26cb87aa-e3d6-4e16-b69b-2e0de7c5e355', 'payment_status' => 'pending']);
        return $order;
    }

    private function stripe(Order $order, string $initial = 'requires_payment_method'): void
    {
        config(['services.stripe.secret' => 'sk_test_fixture']);
        $status = $initial;
        $client = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $client->shouldReceive('request')->andReturnUsing(function ($method, $url) use ($order, &$status) {
            if (str_ends_with($url, '/cancel')) $status = 'canceled';
            return [json_encode(['id' => 'pi_recovery', 'object' => 'payment_intent', 'amount' => 1000,
                'amount_received' => $status === 'succeeded' ? 1000 : 0, 'currency' => 'gbp', 'status' => $status,
                'client_secret' => 'do-not-store-secret', 'livemode' => false,
                'metadata' => ['order_id' => (string) $order->id, 'order_ref' => $order->ref]]), 200, []];
        });
        \Stripe\ApiRequestor::setHttpClient($client);
    }

    public function test_lost_order_response_replays_one_order_and_keeps_capability_private(): void
    {
        $payload = $this->payload();
        $first = $this->postJson('/api/orders', $payload)->assertOk()->assertJsonPath('status', 'success');
        $id = $first->json('data.id');
        $this->assertStringNotContainsString(self::TOKEN, $first->getContent());
        $this->assertArrayNotHasKey('checkout_token_hash', $first->json('data'));
        $this->postJson('/api/orders', array_reverse($payload, true))->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderPayment::count());
        $payload['payment']['amount'] = 20;
        $this->postJson('/api/orders', $payload)->assertStatus(409);
        $this->assertSame(1, Order::count());
    }

    public function test_recovery_requires_the_unguessable_capability_and_returns_no_customer_details(): void
    {
        $order = $this->order();
        $this->postJson('/api/checkout/recover', ['checkout_token' => str_repeat('b', 64), 'order_id' => $order->id, 'order_ref' => $order->ref])->assertNotFound();
        $this->postJson('/api/checkout/recover', [])->assertStatus(422);
        $response = $this->postJson('/api/checkout/recover', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'pending');
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_new_checkout_cannot_use_payment_or_delete_routes_without_capability(): void
    {
        $order = $this->order('stripe', 'pi_recovery');
        $this->postJson('/api/stripe/payment-intend', ['order_id' => $order->id, 'order_ref' => $order->ref])->assertNotFound();
        $this->postJson('/api/stripe/'.$order->id.'/payment', ['payment_id' => 'pi_recovery'])->assertNotFound();
        $this->postJson('/api/orders/'.$order->id.'/ref/'.$order->ref.'/remove')->assertStatus(409);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_recovery_detects_payment_completed_before_browser_confirmation(): void
    {
        $order = $this->order('stripe', 'pi_recovery');$this->stripe($order, 'succeeded');
        $this->postJson('/api/checkout/recover', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'paid');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->postJson('/api/checkout/cancel', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'paid');
        $this->assertSame(1, OrderPayment::count());
    }

    public function test_provider_unavailable_does_not_allow_replacement_or_cancellation(): void
    {
        $order = $this->order('stripe', 'pi_recovery');
        $client = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $client->shouldReceive('request')->andThrow(new \RuntimeException('offline'));
        \Stripe\ApiRequestor::setHttpClient($client);
        foreach (['recover', 'cancel'] as $operation) $this->postJson('/api/checkout/'.$operation, ['checkout_token' => self::TOKEN])->assertStatus(503);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_cancel_card_verifies_remote_cancellation_before_releasing_checkout(): void
    {
        $order = $this->order('stripe', 'pi_recovery');$this->stripe($order);
        $this->postJson('/api/checkout/cancel', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 0, 'payment_status' => 'canceled']);
    }

    public function test_paypal_cancel_prevents_a_stale_popup_from_capturing(): void
    {
        $order = $this->order('paypal', 'PPORDERRECOVERY123');
        $gateway = Mockery::mock(PaypalGateway::class);$gateway->shouldReceive('ready')->andReturn(true);
        $gateway->shouldReceive('request')->once()->with('GET', '/v2/checkout/orders/PPORDERRECOVERY123')->andReturn([
            'id' => 'PPORDERRECOVERY123', 'intent' => 'CAPTURE', 'status' => 'APPROVED', 'purchase_units' => [[
                'reference_id' => (string) $order->id, 'custom_id' => '26cb87aa-e3d6-4e16-b69b-2e0de7c5e355',
                'payee' => ['merchant_id' => 'MERCHANTFIXTURE'], 'amount' => ['currency_code' => 'GBP', 'value' => '10.00'],
            ]],
        ]);
        config(['services.paypal.merchant_id' => 'MERCHANTFIXTURE']);$this->app->instance(PaypalGateway::class, $gateway);
        $this->postJson('/api/checkout/cancel', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->postJson('/api/paypal/'.$order->id.'/capture', ['checkout_token' => self::TOKEN, 'order_ref' => $order->ref, 'paypal_order_id' => 'PPORDERRECOVERY123'])->assertStatus(422);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 0]);
    }
    public function test_cancelled_order_cannot_initialise_another_provider_payment(): void
    {
        $order = $this->order();
        $order->update(['status' => 'cancelled']);
        $this->postJson('/api/stripe/payment-intend', ['checkout_token' => self::TOKEN, 'order_id' => $order->id, 'order_ref' => $order->ref])->assertStatus(422);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_card_initialisation_rechecks_status_after_acquiring_payment_lock(): void
    {
        $order = $this->order();
        OrderPayment::retrieved(function ($payment) use ($order) {
            if ($payment->order_id === $order->id) Order::whereKey($order->id)->update(['status' => 'cancelled']);
        });
        try {
            $this->postJson('/api/stripe/payment-intend', ['checkout_token' => self::TOKEN, 'order_id' => $order->id, 'order_ref' => $order->ref])->assertStatus(422);
        } finally { OrderPayment::flushEventListeners(); }
    }

    public function test_paypal_initialisation_rechecks_status_between_its_two_transactions(): void
    {
        $order = $this->order('paypal');
        $gateway = Mockery::mock(PaypalGateway::class);$gateway->shouldReceive('ready')->andReturn(true);
        $gateway->shouldNotReceive('request');$this->app->instance(PaypalGateway::class, $gateway);
        $reads = 0;
        OrderPayment::retrieved(function ($payment) use ($order, &$reads) {
            if ($payment->order_id === $order->id && ++$reads === 2) Order::whereKey($order->id)->update(['status' => 'cancelled']);
        });
        try {
            $this->postJson('/api/paypal/create-order', ['checkout_token' => self::TOKEN, 'order_id' => $order->id, 'order_ref' => $order->ref])->assertStatus(422);
        } finally { OrderPayment::flushEventListeners(); }
    }

    public function test_paid_order_is_recovered_even_if_administration_archived_it(): void
    {
        $order = $this->order('stripe', 'pi_recovery');$this->stripe($order, 'succeeded');$order->delete();
        $this->postJson('/api/checkout/recover', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'paid');
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 1]);
    }

    public function test_pending_paypal_capture_cannot_be_cancelled_to_start_a_replacement_payment(): void
    {
        $order = $this->order('paypal', 'PPORDERRECOVERY123');
        $gateway = Mockery::mock(PaypalGateway::class);$gateway->shouldReceive('ready')->andReturn(true);
        $gateway->shouldReceive('request')->once()->with('GET', '/v2/checkout/orders/PPORDERRECOVERY123')->andReturn([
            'id' => 'PPORDERRECOVERY123', 'intent' => 'CAPTURE', 'status' => 'COMPLETED', 'purchase_units' => [[
                'reference_id' => (string) $order->id, 'custom_id' => '26cb87aa-e3d6-4e16-b69b-2e0de7c5e355',
                'payee' => ['merchant_id' => 'MERCHANTFIXTURE'], 'amount' => ['currency_code' => 'GBP', 'value' => '10.00'],
                'payments' => ['captures' => [['id' => 'PPCAPTUREPENDING1', 'status' => 'PENDING', 'final_capture' => true,
                    'amount' => ['currency_code' => 'GBP', 'value' => '10.00']]]],
            ]],
        ]);
        config(['services.paypal.merchant_id' => 'MERCHANTFIXTURE']);$this->app->instance(PaypalGateway::class, $gateway);
        $this->postJson('/api/checkout/cancel', ['checkout_token' => self::TOKEN])->assertStatus(409);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 0]);
    }

    public function test_cancel_before_order_creation_blocks_a_late_request_with_the_same_token(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/checkout/cancel', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->postJson('/api/orders', $payload)->assertStatus(409);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderPayment::count());
    }

    public function test_rejected_draft_can_be_cancelled_before_retrying_a_changed_basket(): void
    {
        $payload = $this->payload();$payload['payment']['amount'] = 1;
        $this->postJson('/api/orders', $payload)->assertStatus(422);
        $this->postJson('/api/checkout/cancel', ['checkout_token' => self::TOKEN])->assertOk()->assertJsonPath('data.state', 'cancelled');
        $payload['checkout_token'] = str_repeat('b', 64);$payload['payment']['amount'] = 10;
        $this->postJson('/api/orders', $payload)->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(1, Order::count());
    }

}
