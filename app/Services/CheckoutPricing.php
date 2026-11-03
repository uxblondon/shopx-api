<?php

namespace App\Services;

use App\Models\CollectionPoint;
use App\Models\CollectionRate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckoutPricing
{
    public function calculate(array $payload): array
    {
        Validator::make($payload, [
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email|max:255',
            'payment.type' => 'required|in:stripe,paypal',
            'payment.amount' => 'required|numeric|min:0.50|max:999999.99',
            'payment.billing' => 'required|array',
            'payment.billing.name' => 'required|string|max:255',
            'payment.billing.address_line_1' => 'required|string|max:255',
            'payment.billing.address_line_2' => 'nullable|string|max:255',
            'payment.billing.city' => 'required|string|max:255',
            'payment.billing.county' => 'nullable|string|max:255',
            'payment.billing.postcode' => 'required|string|max:255',
            'payment.billing.country' => 'required|string|size:2|alpha',
            'payment.billing.email' => 'nullable|email|max:255',
            'shipping.method' => 'required|in:delivery,collection',
            'shipping.delivery_address' => 'required_if:shipping.method,delivery|array',
            'shipping.delivery_address.name' => 'required_if:shipping.method,delivery|string|max:255',
            'shipping.delivery_address.address_line_1' => 'required_if:shipping.method,delivery|string|max:255',
            'shipping.delivery_address.address_line_2' => 'nullable|string|max:255',
            'shipping.delivery_address.city' => 'required_if:shipping.method,delivery|string|max:255',
            'shipping.delivery_address.county' => 'nullable|string|max:255',
            'shipping.delivery_address.postcode' => 'required_if:shipping.method,delivery|string|max:255',
            'shipping.delivery_address.country' => 'required_if:shipping.method,delivery|string|size:2|alpha',
            'shipping.deliveries' => 'required_if:shipping.method,delivery|array|min:1|max:100',
            'shipping.deliveries.*.delivery' => 'required|integer|min:0|distinct',
            'shipping.deliveries.*.items' => 'required|array|min:1|max:100',
            'shipping.deliveries.*.items.*.id' => 'required|integer|min:1|distinct',
            'shipping.deliveries.*.items.*.quantity' => 'required|integer|min:1|max:999',
            'shipping.deliveries.*.option' => 'required|array',
            'shipping.deliveries.*.option.rate_id' => 'sometimes|required|integer|min:1',
            'shipping.deliveries.*.option.shipping_option_id' => 'sometimes|required|integer|min:1',
            'shipping.deliveries.*.option.id' => 'sometimes|required|integer|min:1',
            'shipping.collection_address.id' => 'required_if:shipping.method,collection|integer|min:1',
            'shipping.collection_option.id' => 'required_if:shipping.method,collection|integer|min:1',
        ])->validate();
        $items = app(CheckoutCatalogue::class)->items($payload['basket_items'] ?? null, true);
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += CheckoutMoney::pence($item['price']) * $item['quantity'];
        }
        $shipping = $payload['shipping'];
        $shippingCost = 0;
        if ($shipping['method'] === 'collection') {
            foreach ($items as $item) {
                if (! $item['collectable']) {
                    $this->unavailable('An item cannot be collected. Please choose delivery.');
                }
            }
            $point = CollectionPoint::where('id', $shipping['collection_address']['id'])->where('active', 1)->first();
            $rate = $point ? CollectionRate::where('id', $shipping['collection_option']['id'])
                ->where('collection_point_id', $point->id)->where('available', 1)->first() : null;
            if (! $rate) {
                $this->unavailable('This collection option is no longer available. Please choose it again.');
            }
            $shipping['collection_address'] = $point->toArray();
            $shipping['collection_option'] = $rate->toArray();
            $shippingCost = CheckoutMoney::pence($rate->cost);
        } else {
            $country = strtoupper($shipping['delivery_address']['country']);
            $shipping['delivery_address']['country'] = $country;
            $packages = app(DeliveryQuote::class)->packages($items, $country);
            $selected = array_column($shipping['deliveries'], null, 'delivery');
            if (count($selected) !== count($packages)) {
                $this->unavailable();
            }
            $deliveries = [];
            foreach ($packages as $index => $package) {
                $selection = $selected[$index] ?? null;
                if (! $selection || ! $package['size'] || ! $package['options']
                    || $this->identities($package['items']) !== $this->identities($selection['items'])) {
                    $this->unavailable();
                }
                $choice = $selection['option'];
                $options = array_values(array_filter($package['options'], function ($option) use ($choice) {
                    // rate_id is unambiguous. Older baskets only have the carrier option ID.
                    if (isset($choice['rate_id'])) {
                        return (int) $option['rate_id'] === (int) $choice['rate_id'];
                    }
                    $id = $choice['shipping_option_id'] ?? $choice['id'] ?? null;
                    return $id !== null && (int) $option['shipping_option_id'] === (int) $id;
                }));
                if (count($options) !== 1) {
                    $this->unavailable();
                }
                $option = $options[0];
                $shippingCost += CheckoutMoney::pence($option['cost']);
                $deliveries[] = ['delivery' => $index, 'items' => $package['items'], 'option' => $option];
            }
            $shipping['deliveries'] = $deliveries;
        }
        $total = $subtotal + $shippingCost;
        if ($total < 50 || $total > 99999999) {
            $this->unavailable('The order total is outside the supported payment range.');
        }
        if (CheckoutMoney::pence($payload['payment']['amount']) !== $total) {
            throw ValidationException::withMessages(['payment.amount' => 'Prices or shipping charges have changed. Please review your basket and choose shipping again before paying.']);
        }
        $shipping['cost'] = CheckoutMoney::pounds($shippingCost);
        return ['items' => $items, 'shipping' => $shipping, 'subtotal' => CheckoutMoney::pounds($subtotal),
            'shipping_cost' => CheckoutMoney::pounds($shippingCost), 'total' => CheckoutMoney::pounds($total)];
    }

    private function identities(array $items): array
    {
        $identities = [];
        foreach ($items as $item) {
            $identities[] = (int) $item['id'].':'.(int) $item['quantity'];
        }
        sort($identities);
        return $identities;
    }

    private function unavailable(string $message = 'Delivery options have changed. Please choose shipping again.'): never
    {
        throw ValidationException::withMessages(['shipping' => $message]);
    }
}
