<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test_fixture', 'services.stripe.webhook_secret' => 'whsec_fixture']);
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(new \Stripe\HttpClient\CurlClient());
        parent::tearDown();
    }

    private function order(): Order
    {
        $order = Order::create(['ref' => 'SANDBOX-TEST', 'name' => 'Stripe Test', 'email' => 'stripe@example.test']);
        OrderPayment::create(['order_id' => $order->id, 'payment_type' => 'stripe', 'amount' => 10,
            'payment_id' => 'pi_fixture', 'payment_status' => 'pending']);
        return $order;
    }

    private function intent(Order $order, array $overrides = []): array
    {
        return array_replace([
            'id' => 'pi_fixture', 'object' => 'payment_intent', 'amount' => 1000,
            'amount_received' => 1000, 'currency' => 'gbp', 'status' => 'succeeded',
            'client_secret' => 'fixture_secret', 'livemode' => false,
            'metadata' => ['order_id' => (string) $order->id, 'order_ref' => $order->ref],
        ], $overrides);
    }

    private function stripeResponse(array $intent, int $times = 1): void
    {
        $client = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $client->shouldReceive('request')->times($times)->andReturn([json_encode($intent), 200, []]);
        \Stripe\ApiRequestor::setHttpClient($client);
    }

    private function event(array $intent, string $type = 'payment_intent.succeeded')
    {
        $body = json_encode(['id' => 'evt_fixture', 'object' => 'event', 'type' => $type,
            'data' => ['object' => $intent]]);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$body, 'whsec_fixture');
        return $this->call('POST', '/api/stripe-payment-status', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$signature], $body);
    }

    public function test_browser_cannot_claim_an_incomplete_payment_succeeded(): void
    {
        $order = $this->order();
        $this->stripeResponse($this->intent($order, ['status' => 'requires_action', 'amount_received' => 0]));
        $this->postJson('/api/stripe/'.$order->id.'/payment', ['payment_id' => 'pi_fixture', 'payment_status' => 'succeeded'])
            ->assertStatus(409)->assertJsonPath('payment_status', 'requires_action');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 0]);
    }

    public function test_verified_payment_without_charges_array_is_confirmed_idempotently(): void
    {
        $order = $this->order();
        $this->stripeResponse($this->intent($order), 2);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/stripe/'.$order->id.'/payment', ['payment_id' => 'pi_fixture', 'payment_status' => 'failed'])
                ->assertOk()->assertJsonPath('payment_status', 'succeeded');
        }
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_confirmed' => 1]);
        $this->assertSame(1, OrderPayment::count());
    }

    public function test_another_intent_cannot_be_attached_to_the_order(): void
    {
        $order = $this->order();
        $this->postJson('/api/stripe/'.$order->id.'/payment', ['payment_id' => 'pi_other'])
            ->assertStatus(422);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_wrong_amount_currency_or_order_metadata_cannot_confirm_payment(): void
    {
        foreach ([['amount' => 100], ['currency' => 'usd'], ['amount_received' => 100],
                  ['metadata' => ['order_id' => 'other', 'order_ref' => 'wrong']]] as $change) {
            $order = $this->order();
            $this->stripeResponse($this->intent($order, $change));
            $this->postJson('/api/stripe/'.$order->id.'/payment', ['payment_id' => 'pi_fixture'])->assertStatus(422);
            $this->assertSame('pending', $order->fresh()->status);
            $order->delete();
            OrderPayment::where('order_id', $order->id)->delete();
        }
    }

    public function test_unsigned_webhook_cannot_change_a_payment(): void
    {
        $order = $this->order();
        $this->postJson('/api/stripe-payment-status', ['type' => 'payment_intent.succeeded',
            'data' => ['object' => $this->intent($order)]])->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_signed_webhook_confirms_once_and_old_failure_cannot_downgrade_it(): void
    {
        $order = $this->order();
        $this->event($this->intent($order))->assertOk();
        $this->event($this->intent($order))->assertOk();
        $this->event($this->intent($order, ['status' => 'requires_payment_method', 'amount_received' => 0]), 'payment_intent.payment_failed')->assertOk();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_status' => 'succeeded', 'payment_confirmed' => 1]);
    }

    public function test_unsigned_or_unconfigured_paypal_cannot_create_confirmed_orders(): void
    {
        config(['services.paypal.enabled' => false]);
        $this->postJson('/api/orders', ['payment' => ['type' => 'paypal']])->assertStatus(422);
        $this->postJson('/api/paypal-payment-status', [])->assertStatus(503);
        $this->assertSame(0, Order::count());
    }

    public function test_intent_amount_comes_from_saved_payment_and_intent_is_saved_before_response(): void
    {
        $order = $this->order();
        OrderPayment::where('order_id', $order->id)->update(['payment_id' => '']);
        $intent = $this->intent($order, ['status' => 'requires_payment_method', 'amount_received' => 0]);
        $client = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $client->shouldReceive('request')->once()->withArgs(function ($method, $url, $headers, $params) {
            return strtolower($method) === 'post' && (int) $params['amount'] === 1000;
        })->andReturn([json_encode($intent), 200, []]);
        \Stripe\ApiRequestor::setHttpClient($client);
        $this->postJson('/api/stripe/payment-intend', ['order_id' => $order->id, 'order_ref' => $order->ref, 'amount' => 1])
            ->assertOk()->assertJsonPath('secret', 'fixture_secret');
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_id' => 'pi_fixture']);
    }
}
