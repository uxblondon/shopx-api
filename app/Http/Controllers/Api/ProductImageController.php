<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, $product_id)
    {
        $product = Product::findOrFail($product_id);
        $data = $this->validated($request, true);
        $path = null;
        try {
            $path = $request->file('image')->store('products/'.$product->id, config('filesystems.default'));
            $image = ProductImage::create([
                'product_id' => $product->id, 'description' => $data['description'] ?? null,
                'location' => Storage::url($path), 'feature_image' => $request->boolean('feature_image'),
            ]);
        } catch (\Throwable $error) {
            if ($path) Storage::disk(config('filesystems.default'))->delete($path);
            return response()->json(['status' => 'error', 'message' => 'Failed to upload product image.'], 503);
        }
        return response()->json(['status' => 'success', 'message' => 'Product image uploaded successfully.', 'data' => $image]);
    }

    public function update(Request $request, $product_id, $image_id)
    {
        $image = ProductImage::where('product_id', $product_id)->findOrFail($image_id);
        $data = $this->validated($request, false);
        $path = null;
        try {
            $values = ['description' => $data['description'] ?? null];
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products/'.$image->product_id, config('filesystems.default'));
                $values['location'] = Storage::url($path);
            }
            if ($request->has('feature_image')) $values['feature_image'] = $request->boolean('feature_image');
            $oldPath = $this->storagePath($image->location, $image->product_id);
            $image->update($values);
        } catch (\Throwable $error) {
            if ($path) Storage::disk(config('filesystems.default'))->delete($path);
            return response()->json(['status' => 'error', 'message' => 'Failed to update product image.'], 503);
        }
        // Commit the new database reference before removing the old object.
        if ($path && $oldPath && $oldPath !== $path) Storage::disk(config('filesystems.default'))->delete($oldPath);
        return response()->json(['status' => 'success', 'message' => 'Product image updated successfully.', 'data' => $image->fresh()]);
    }

    public function destroy($product_id, $image_id)
    {
        $image = ProductImage::where('product_id', $product_id)->findOrFail($image_id);
        if ($path = $this->storagePath($image->location, $image->product_id)) Storage::disk(config('filesystems.default'))->delete($path);
        $image->delete();
        return response()->json(['status' => 'success', 'message' => 'Product image deleted successfully.']);
    }

    private function validated(Request $request, bool $required): array
    {
        return $request->validate([
            'image' => [$required ? 'required' : 'sometimes', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480', function ($attribute, $file, $fail) {
                if (@getimagesize($file->getRealPath()) === false) $fail('Upload a valid raster image.');
            }],
            'description' => ['nullable', 'string', 'max:5000'],
            'feature_image' => ['sometimes', 'boolean'],
        ]);
    }

    private function storagePath($location, $productId): ?string
    {
        $prefix = rtrim(Storage::url(''), '/').'/';
        $path = str_starts_with($location, $prefix) ? substr($location, strlen($prefix)) : $location;
        // Delete only this product's objects on the configured disk, never arbitrary keys/URLs.
        if (str_contains($path, '..') || ! preg_match('~^products/'.(int) $productId.'/[^/]+$~', $path)) return null;
        return $path;
    }
}
