<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckoutCatalogue
{
    public function items($submitted, bool $lock = false): array
    {
        Validator::make(['items' => $submitted], [
            'items' => 'required|array|min:1|max:100',
            'items.*' => 'required|array',
            'items.*.id' => 'required|integer|min:1|distinct',
            'items.*.product_id' => 'sometimes|required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1|max:999',
        ])->validate();
        $result = [];
        // A stable lock order avoids deadlocks between baskets with the same variants.
        $ids = array_column($submitted, 'id');
        sort($ids, SORT_NUMERIC);
        $variants = ProductVariant::whereIn('id', $ids)->orderBy('id');
        if ($lock) {
            $variants->lockForUpdate();
        }
        $variants = $variants->get()->keyBy('id');
        foreach ($submitted as $item) {
            $variant = $variants->get($item['id']);
            $product = $variant ? Product::find($variant->product_id) : null;
            if (! $product || $product->status !== 'published'
                || (isset($item['product_id']) && (int) $item['product_id'] !== (int) $product->id)) {
                throw ValidationException::withMessages(['basket_items' => 'An item is no longer available. Please review your basket.']);
            }
            if ($variant->track_inventory && (int) $variant->stock < (int) $item['quantity']) {
                throw ValidationException::withMessages(['basket_items' => 'The requested quantity is no longer available. Please review your basket.']);
            }
            CheckoutMoney::pence($variant->price);
            CheckoutMoney::pence($variant->additional_shipping_cost);
            $canonical = $variant->toArray();
            $canonical['title'] = $product->title;
            $canonical['quantity'] = (int) $item['quantity'];
            $canonical['separated_shipping_required'] = (int) $variant->separated_shipping_required;
            foreach ([1, 2, 3] as $number) {
                $type = $variant->{'variant_'.$number.'_id'};
                $canonical['variant_'.$number.'_name'] = $type ? ProductVariantType::find($type)?->name : null;
            }
            $result[] = $canonical;
        }
        return $result;
    }
}
