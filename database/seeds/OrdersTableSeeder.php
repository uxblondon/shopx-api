<?php

use Illuminate\Database\Seeder;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryAddress;

use App\Models\ProductVariant;

class OrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fake = Faker\Factory::create();
        $payment_types = ['Paypal', 'Stripe'];
        $countries = [
            "AL",
            "AD",
            "AZ",
            "AT",
            "AM",
            "BE",
            "BA",
            "BG",
            "BY",
            "HR",
            "CY",
            "CZ",
            "DK",
            "EE",
            "FO",
            "FI",
            "AX",
            "FR",
            "GE",
            "DE",
            "GI",
            "GR",
            "VA",
            "HU",
            "IS",
            "IE",
            "IT",
            "KZ",
            "LV",
            "LI",
            "LT",
            "LU",
            "MT",
            "MC",
            "MD",
            "ME",
            "NL",
            "NO",
            "PL",
            "PT",
            "RO",
            "RU",
            "SM",
            "RS",
            "SK",
            "SI",
            "ES",
            "SJ",
            "SE",
            "CH",
            "TR",
            "UA",
            "MK",
            "GB",
            "GG",
            "JE",
            "IM",
          ];
        

        for ($u = 0; $u < 100; $u++) {
            $order_data = array(
                'ref' => rand(999, 1000),
                'name' => $fake->name,
                'email' => $fake->safeEmail,
                'created_at' => date('Y-m-d H:i:s')
            );
            $order = Order::create($order_data);

            for ($v = 0; $v < rand(2, 7); $v++) {

                $variant = ProductVariant::leftJoin('product_variant_types as variant_1', function ($join) {
                    $join->on('variant_1.id', 'product_variants.variant_1_id')->whereNull('variant_1.deleted_at');
                })
                    ->leftJoin('product_variant_types as variant_2', function ($join) {
                        $join->on('variant_2.id', 'product_variants.variant_2_id')->whereNull('variant_2.deleted_at');
                    })
                    ->leftJoin('product_variant_types as variant_3', function ($join) {
                        $join->on('variant_3.id', 'product_variants.variant_3_id')->whereNull('variant_3.deleted_at');
                    })
                    ->where('product_variants.id', rand(1, 57))
                    ->first([
                        'product_variants.id as variant_id',
                        'product_variants.product_id',
                        'product_variants.sku',
                        'product_variants.price',
                        'product_variants.weight',
                        'product_variants.length',
                        'product_variants.width',
                        'product_variants.height',
                        'product_variants.shipping_not_required',
                        'product_variants.separated_shipping_required',
                        'product_variants.additional_shipping_cost',
                        'variant_1.name as variant_1_name',
                        'product_variants.variant_1_value as variant_1_value',
                        'variant_2.name as variant_2_name',
                        'product_variants.variant_2_value as variant_2_value',
                        'variant_3.name as variant_3_name',
                        'product_variants.variant_3_value as variant_3_value',
                    ])->toArray();

                $order_item_data = $variant;
                $order_item_data['order_id'] = $order->id;
                $order_item_data['created_at'] = date('Y-m-d H:i:s');

                OrderItem::create($order_item_data);
            }

            // order payment 
            $order_payment_data = array(
                'order_id' => $order->id,
                'payment_type' => $payment_types[rand(0, 1)],
                'amount' => rand(99, 999),
                'payment_id' => rand(1000, 10000),
                'payment_status' => 'completed',
                'created_at' => date('Y-m-d H:i:s'),
            );

            OrderPayment::create($order_payment_data);

            // order delivey 
            $order_delivery_data = array(
                'order_id' => $order->id,
                'shipping_rate_id' => rand(1, 5),
                'shipping_label' => 'Shipping Zone - Provier Service',
                'different_billing_address' => rand(0, 1),
                'cost' => rand(20, 30),
                'additional_cost' => rand(5, 10),
                'created_at' => date('Y-m-d H:i:s'),
            );

            $order_delivery = OrderDelivery::create($order_delivery_data);

            $shipping_address_data = array(
                'order_id' => $order->id,
                'type' => 'shipping',
                'name' => $order->name,
                'phone' => $fake->phoneNumber,
                'address_line_1' => $fake->streetName,
                'city' => $fake->city,
                'postcode' => $fake->postcode,
                'country_code' => $countries[rand(0, count($countries)-1)],
            );

            OrderDeliveryAddress::create($shipping_address_data);

            if($order_delivery->different_billing_address === 1) {
                $billing_address_data = array(
                    'order_id' => $order->id,
                    'type' => 'billing',
                    'name' => $order->name,
                    'phone' => $fake->phoneNumber,
                    'address_line_1' => $fake->streetName,
                    'city' => $fake->city,
                    'postcode' => $fake->postcode,
                    'country_code' => $countries[rand(0, count($countries)-1)],
                );
    
                OrderDeliveryAddress::create($billing_address_data);
            }
        }
    }
}
