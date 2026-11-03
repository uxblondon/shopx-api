<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Mail\OrderNotification;
use App\Models\CollectionPoint;
use App\Models\CollectionRate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCountry;
use App\Models\ShippingOption;
use App\Models\ShippingPackageSize;
use App\Models\ShippingZone;
use App\Models\ShippingZoneProduct;
use App\Models\ProductShippingOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_shipping_rates_feed_the_public_delivery_quote(): void
    {
        $user = User::factory()->create(['admin' => 1]);
        $this->actingAs($user, 'api');
        $zone = ShippingZone::create(['title' => 'UK', 'available' => 1, 'created_by' => $user->id]);
        ShippingCountry::create(['shipping_zone_id' => $zone->id, 'country_code' => 'GB', 'created_by' => $user->id]);
        $size = ShippingPackageSize::create(['format' => 'Parcel', 'width' => 100, 'length' => 100, 'height' => 100, 'max_weight' => 100, 'created_by' => $user->id]);
        $option = ShippingOption::create(['provider' => 'Test carrier', 'service' => 'Tracked', 'speed' => '2 days', 'created_by' => $user->id]);
        $rate = ['package_size_id' => $size->id, 'shipping_option_id' => $option->id, 'cost_based_on' => 'basket_value', 'min_value' => 0, 'max_value' => 1000, 'cost' => 5, 'available' => 1];
        $url = '/api/shipping-zones/'.$zone->id.'/rates';
        $create = $this->postJson($url, $rate)->assertOk()->assertJsonPath('status', 'success');
        $id = $create->json('data.id');
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'success')->assertJsonCount(1, 'data');
        $this->putJson($url.'/'.$id, array_merge($rate, ['cost' => 6]))->assertOk()->assertJsonPath('status', 'success');
        $product = Product::create(['title' => 'Gift', 'slug' => 'gift', 'description' => 'Gift', 'created_by' => $user->id]);
        ShippingZoneProduct::create(['product_id' => $product->id, 'shipping_zone_id' => $zone->id, 'created_by' => $user->id]);
        ProductShippingOption::create(['product_id' => $product->id, 'shipping_option_id' => $option->id, 'created_by' => $user->id]);
        $this->postJson('/api/delivery-options', ['address' => ['country' => 'GB'], 'items' => [[
            'product_id' => $product->id, 'title' => 'Gift', 'width' => 1, 'length' => 1, 'height' => 1, 'weight' => 1,
            'price' => 10, 'quantity' => 1, 'additional_shipping_cost' => 0, 'separated_shipping_required' => 0,
        ]]])->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('data.0.options.0.cost', 6);
    }

    public function test_collection_order_is_saved_and_visible_to_administration(): void
    {
        $product = Product::create(['title' => 'Gift', 'slug' => 'gift', 'description' => 'Gift', 'created_by' => 1]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'price' => 10, 'width' => 1, 'length' => 1, 'height' => 1, 'created_by' => 1]);
        $point = CollectionPoint::create(['name' => 'Test collection', 'address_line_1' => '1 Test Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country_code' => 'GB', 'active' => 1, 'created_by' => 1]);
        CollectionRate::create(['collection_point_id' => $point->id, 'speed' => 'Next day', 'cost' => 0, 'available' => 1, 'created_by' => 1]);
        $this->postJson('/api/collection-options', ['collection_point_id' => $point->id])->assertOk()->assertJsonCount(1, 'data');
        $item = array_merge($variant->fresh()->toArray(), ['title' => 'Gift', 'quantity' => 1,
            'variant_1_name' => null, 'variant_2_name' => null, 'variant_3_name' => null]);
        $address = ['name' => 'Test Customer', 'address_line_1' => '1 Test Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country_code' => 'GB', 'country' => 'GB'];
        $payload = ['customer' => ['name' => 'Test Customer', 'email' => 'customer@example.com'], 'basket_items' => [$item],
            'shipping' => ['method' => 'collection', 'collection_option' => ['speed' => 'Next day', 'cost' => 0], 'collection_address' => $address],
            'payment' => ['type' => 'stripe', 'amount' => 10, 'billing' => $address]];
        $response = $this->postJson('/api/orders', $payload)->assertOk()->assertJsonPath('status', 'success');
        $id = $response->json('data.id');
        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseHas('order_items', ['order_id' => $id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $this->assertDatabaseHas('order_payments', ['order_id' => $id, 'payment_type' => 'stripe', 'payment_confirmed' => 0]);
        $this->actingAs(User::factory()->create(['admin' => 1]), 'api');
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('data.0.id', $id);
        $details = $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonPath('data.payment.payment_type', 'stripe');
        $order = json_decode(json_encode($details->json('data')));
        $this->assertStringContainsString($order->ref, (new OrderConfirmation($order))->render());
        $this->assertStringContainsString($order->ref, (new OrderNotification($order))->render());
    }

    public function test_stripe_intent_response_remains_compatible_without_a_live_payment(): void
    {
        config(['services.stripe.secret' => 'sk_test_local_fixture']);
        $order = Order::create(['ref' => 'TEST', 'name' => 'Test Customer', 'email' => 'stripe@example.test']);
        \App\Models\OrderPayment::create(['order_id' => $order->id, 'payment_type' => 'stripe', 'amount' => 10]);
        $client = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $client->shouldReceive('request')->once()->andReturn([
            json_encode(['id' => 'pi_fixture', 'object' => 'payment_intent', 'client_secret' => 'fixture_secret', 'status' => 'requires_payment_method', 'currency' => 'gbp', 'amount' => 1000, 'livemode' => false, 'metadata' => ['order_id' => (string) $order->id, 'order_ref' => 'TEST']]), 200, [],
        ]);
        \Stripe\ApiRequestor::setHttpClient($client);
        try {
            $this->postJson('/api/stripe/payment-intend', ['order_id' => $order->id, 'order_ref' => 'TEST', 'amount' => 1000])
                ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('secret', 'fixture_secret');
        } finally {
            \Stripe\ApiRequestor::setHttpClient(new \Stripe\HttpClient\CurlClient());
        }
    }
}
