<?php

namespace Tests\Feature;

use App\Models\CollectionPoint;
use App\Models\CollectionRate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\ShippingCountry;
use App\Models\ShippingOption;
use App\Models\ShippingPackageSize;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPricingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $product = Product::create(['title' => 'Saved gift', 'slug' => 'saved-gift', 'status' => 'published', 'description' => 'Gift', 'created_by' => 1]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'price' => '10.01', 'width' => 1, 'length' => 1, 'height' => 1,
            'weight' => 1, 'additional_shipping_cost' => '0.10', 'created_by' => 1])->fresh();
        $zone = ShippingZone::create(['title' => 'UK', 'available' => 1, 'created_by' => 1]);
        ShippingCountry::create(['shipping_zone_id' => $zone->id, 'country_code' => 'GB', 'created_by' => 1]);
        ShippingZoneProduct::create(['product_id' => $product->id, 'shipping_zone_id' => $zone->id, 'created_by' => 1]);
        $option = ShippingOption::create(['provider' => 'Saved carrier', 'service' => 'Tracked', 'speed' => '2 days', 'created_by' => 1]);
        ProductShippingOption::create(['product_id' => $product->id, 'shipping_option_id' => $option->id, 'created_by' => 1]);
        $size = ShippingPackageSize::create(['format' => 'Parcel', 'width' => 100, 'length' => 100, 'height' => 100, 'max_weight' => 100, 'created_by' => 1]);
        // Ensure rate_id differs from the carrier ID (legacy joins returned the latter as id).
        ShippingRate::create(['shipping_zone_id' => $zone->id, 'package_size_id' => $size->id, 'shipping_option_id' => $option->id,
            'cost_based_on' => 'basket_value', 'max_value' => 1000, 'cost' => 99, 'available' => 0, 'created_by' => 1]);
        $rate = ShippingRate::create(['shipping_zone_id' => $zone->id, 'package_size_id' => $size->id, 'shipping_option_id' => $option->id,
            'cost_based_on' => 'basket_value', 'max_value' => 1000, 'cost' => '1.77', 'available' => 1, 'created_by' => 1]);
        $point = CollectionPoint::create(['name' => 'Saved collection', 'address_line_1' => '1 Test Street', 'city' => 'London',
            'postcode' => 'SW1A 1AA', 'country_code' => 'GB', 'active' => 1, 'created_by' => 1]);
        $collection = CollectionRate::create(['collection_point_id' => $point->id, 'speed' => 'Next day', 'cost' => '0.20', 'available' => 1, 'created_by' => 1]);
        $item = array_merge($variant->toArray(), ['quantity' => 3, 'title' => 'Saved gift']);
        $address = ['name' => 'Sandbox customer', 'address_line_1' => '2 Test Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'];
        $payload = ['customer' => ['name' => 'Sandbox customer', 'email' => 'pricing@example.test'], 'basket_items' => [$item],
            'shipping' => ['method' => 'delivery', 'delivery_address' => $address, 'cost' => '2.07',
                'deliveries' => [['delivery' => 0, 'items' => [$item], 'option' => ['rate_id' => $rate->id, 'shipping_option_id' => $option->id, 'cost' => '2.07']]]],
            'payment' => ['type' => 'stripe', 'amount' => '32.10', 'billing' => $address]];
        return compact('product', 'variant', 'zone', 'option', 'rate', 'size', 'point', 'collection', 'payload');
    }

    public function test_delivery_uses_saved_prices_dimensions_titles_and_charges_in_whole_pence(): void
    {
        $f = $this->fixture(); $p = $f['payload'];
        $p['basket_items'][0] = array_merge($p['basket_items'][0], ['price' => 0.01, 'title' => 'Forged', 'width' => 99999, 'additional_shipping_cost' => 0]);
        $p['shipping']['cost'] = 0;
        $p['shipping']['deliveries'][0]['option'] += ['provider' => 'Forged', 'service' => 'Forged'];
        $p['shipping']['deliveries'][0]['option']['cost'] = 0;
        $r = $this->postJson('/api/orders', $p)->assertOk()->assertJsonPath('data.total_amount', '32.10')->assertJsonPath('data.shipping_cost', '2.07');
        $id = $r->json('data.id');
        $this->assertDatabaseHas('order_items', ['order_id' => $id, 'title' => 'Saved gift', 'price' => '10.01', 'width' => 1, 'quantity' => 3]);
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $id, 'provider' => 'Saved carrier', 'cost' => '2.07']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $id, 'amount' => '32.10', 'payment_confirmed' => 0]);
    }

    public function test_reduced_or_stale_total_is_rejected_before_order_creation(): void
    {
        $f = $this->fixture(); $p = $f['payload']; $p['payment']['amount'] = 1;
        $this->postJson('/api/orders', $p)->assertStatus(422)->assertJsonValidationErrors('payment.amount');
        $p['payment']['amount'] = '32.10'; $f['variant']->update(['price' => '11.01']);
        $this->postJson('/api/orders', $p)->assertStatus(422)->assertJsonValidationErrors('payment.amount');
        $this->assertSame(0, Order::count());
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_shipping_quotes_ignore_supplied_prices_weight_and_surcharges(): void
    {
        $f = $this->fixture(); $item = $f['payload']['basket_items'][0];
        $item['price'] = 0; $item['weight'] = 100000; $item['additional_shipping_cost'] = 0;
        $this->postJson('/api/delivery-options', ['address' => ['country' => 'GB'], 'items' => [$item]])
            ->assertOk()->assertJsonCount(1, 'data.0.options')->assertJsonPath('data.0.options.0.cost', '2.07')
            ->assertJsonPath('data.0.options.0.rate_id', $f['rate']->id);
    }

    public function test_disabled_or_deleted_rates_and_carriers_cannot_be_used(): void
    {
        $f = $this->fixture();
        foreach (['rate', 'option', 'size', 'zone'] as $name) {
            $f[$name]->update(['available' => 0]);
            $this->postJson('/api/orders', $f['payload'])->assertStatus(422);
            $f[$name]->update(['available' => 1]);
        }
        $f['rate']->delete();
        $this->postJson('/api/orders', $f['payload'])->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_wrong_country_and_unassigned_carrier_are_rejected(): void
    {
        $f = $this->fixture(); $p = $f['payload']; $p['shipping']['delivery_address']['country'] = 'US';
        $this->postJson('/api/orders', $p)->assertStatus(422);
        ProductShippingOption::query()->delete();
        $this->postJson('/api/orders', $f['payload'])->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_missing_duplicate_and_altered_package_contents_are_rejected(): void
    {
        $f = $this->fixture();
        foreach (['missing', 'duplicate', 'quantity', 'foreign'] as $change) {
            $p = $f['payload'];
            if ($change === 'missing') $p['shipping']['deliveries'] = [];
            if ($change === 'duplicate') $p['shipping']['deliveries'][] = $p['shipping']['deliveries'][0];
            if ($change === 'quantity') $p['shipping']['deliveries'][0]['items'][0]['quantity'] = 1;
            if ($change === 'foreign') $p['shipping']['deliveries'][0]['items'][0]['id'] = 999;
            $this->postJson('/api/orders', $p)->assertStatus(422);
        }
        $this->assertSame(0, Order::count());
    }

    public function test_duplicate_variants_and_invalid_quantities_are_rejected(): void
    {
        $f = $this->fixture();
        foreach ([0, -1, 1.5, 1000] as $quantity) {
            $p = $f['payload']; $p['basket_items'][0]['quantity'] = $quantity;
            $this->postJson('/api/orders', $p)->assertStatus(422);
        }
        $p = $f['payload']; $p['basket_items'][] = $p['basket_items'][0];
        $this->postJson('/api/orders', $p)->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_unpublished_deleted_mismatched_and_out_of_stock_items_are_rejected(): void
    {
        $f = $this->fixture(); $p = $f['payload'];
        $f['product']->update(['status' => 'draft']);
        $this->postJson('/api/orders', $p)->assertStatus(422);
        $f['product']->update(['status' => 'published']);
        $p['basket_items'][0]['product_id'] = 999;
        $this->postJson('/api/orders', $p)->assertStatus(422);
        $f['variant']->update(['track_inventory' => 1, 'stock' => 2]);
        $this->postJson('/api/orders', $f['payload'])->assertStatus(422);
        $f['variant']->delete();
        $this->postJson('/api/orders', $f['payload'])->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    private function collectionPayload(array $f): array
    {
        $p = $f['payload'];
        $p['shipping'] = ['method' => 'collection', 'collection_address' => array_merge($f['point']->toArray(), ['name' => 'Forged']),
            'collection_option' => ['id' => $f['collection']->id, 'cost' => 0, 'speed' => 'Forged']];
        $p['payment']['amount'] = '30.23';
        return $p;
    }

    public function test_collection_uses_saved_point_rate_and_price(): void
    {
        $f = $this->fixture(); $p = $this->collectionPayload($f);
        $r = $this->postJson('/api/orders', $p)->assertOk()->assertJsonPath('data.total_amount', '30.23');
        $id = $r->json('data.id');
        $this->assertDatabaseHas('order_addresses', ['order_id' => $id, 'name' => 'Saved collection']);
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $id, 'speed' => 'Next day', 'cost' => '0.20']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $id, 'amount' => '30.23']);
    }

    public function test_collection_requires_active_point_available_matching_rate_and_collectable_product(): void
    {
        $f = $this->fixture(); $p = $this->collectionPayload($f);
        $f['point']->update(['active' => 0]); $this->postJson('/api/orders', $p)->assertStatus(422);
        $f['point']->update(['active' => 1]);
        $f['collection']->update(['available' => 0]); $this->postJson('/api/orders', $p)->assertStatus(422);
        $this->postJson('/api/collection-options', ['collection_point_id' => $f['point']->id])->assertOk()->assertJsonCount(0, 'data');
        $f['collection']->update(['available' => 1]);
        $f['variant']->update(['collectable' => 0]); $this->postJson('/api/orders', $p)->assertStatus(422);
        $f['variant']->update(['collectable' => 1]);
        $p['shipping']['collection_address']['id'] = 999;
        $this->postJson('/api/orders', $p)->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_legacy_carrier_selection_is_supported_when_unambiguous(): void
    {
        $f = $this->fixture(); $p = $f['payload'];
        unset($p['shipping']['deliveries'][0]['option']['rate_id']);
        $this->postJson('/api/orders', $p)->assertOk()->assertJsonPath('data.total_amount', '32.10');
    }

    public function test_unknown_payment_methods_cannot_bypass_payment_verification(): void
    {
        $f = $this->fixture(); $p = $f['payload']; $p['payment']['type'] = 'cash';
        $this->postJson('/api/orders', $p)->assertStatus(422);
        $this->assertSame(0, Order::count());
    }
    public function test_multiple_packages_each_require_their_own_available_rate_and_complete_contents(): void
    {
        $f = $this->fixture();
        $f['variant']->update(['separated_shipping_required' => 1]);
        $second = $f['variant']->replicate(); $second->price = '2.00'; $second->save();
        $items = [['id' => $f['variant']->id, 'quantity' => 3], ['id' => $second->id, 'quantity' => 2]];
        $quote = $this->postJson('/api/delivery-options', ['address' => ['country' => 'GB'], 'items' => $items])
            ->assertOk()->assertJsonCount(2, 'data')->json('data');
        $p = $f['payload']; $p['basket_items'] = $items;
        $p['shipping']['deliveries'] = [];
        foreach ($quote as $index => $package) {
            $p['shipping']['deliveries'][] = ['delivery' => $index, 'items' => $package['items'], 'option' => $package['options'][0]];
        }
        $p['payment']['amount'] = '38.07'; // 30.03 + 4.00 + 2.07 + 1.97
        $missing = $p; array_pop($missing['shipping']['deliveries']);
        $this->postJson('/api/orders', $missing)->assertStatus(422);
        $r = $this->postJson('/api/orders', $p)->assertOk()->assertJsonPath('data.total_amount', '38.07');
        $this->assertDatabaseCount('order_deliveries', 2);
        $this->assertDatabaseCount('order_delivery_items', 2);
        $this->assertDatabaseHas('order_payments', ['order_id' => $r->json('data.id'), 'amount' => '38.07']);
    }

    public function test_weight_based_rate_and_surcharge_use_saved_weight_and_quantities(): void
    {
        $f = $this->fixture();
        $f['rate']->update(['cost_based_on' => 'basket_weight', 'min_weight' => 2, 'max_weight' => 4]);
        $p = $f['payload']; $p['basket_items'][0]['weight'] = 999;
        $this->postJson('/api/orders', $p)->assertOk()->assertJsonPath('data.total_amount', '32.10');
        $f['rate']->update(['max_weight' => 2]);
        $this->postJson('/api/orders', $p)->assertStatus(422);
        $this->assertSame(1, Order::count());
    }

    public function test_ambiguous_legacy_carrier_selection_requires_a_fresh_shipping_quote(): void
    {
        $f = $this->fixture(); $p = $f['payload'];
        $other = $f['rate']->replicate(); $other->cost = 9; $other->save();
        unset($p['shipping']['deliveries'][0]['option']['rate_id']);
        $this->postJson('/api/orders', $p)->assertStatus(422);
        // Selecting the exact rate returned by a fresh quote is unambiguous.
        $this->postJson('/api/orders', $f['payload'])->assertOk()->assertJsonPath('data.total_amount', '32.10');
    }

}
