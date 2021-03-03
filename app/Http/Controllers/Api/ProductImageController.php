<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Models\ProductImage;

class ProductImageController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $product_id)
    {

        try {
            $image = $request->file('image');
            $location = str_replace('', '', 'products/' . $product_id . '/' . $image->getClientOriginalName());

            Storage::disk('s3')->put($location, file_get_contents($request->file('image')));

            $image_data = array(
                'product_id' => $product_id,
                'description' => $request->get('description'),
                'location' => Storage::url($location),
            );

            if ($request->has('feature_image')) {
                $image_data['feature_image'] = $request->get('feature_image') ? 1 : 0;
            }

            $product_image = ProductImage::create($image_data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to upload product image.']);
        }

        return response()->json(['status' => 'success', 'message' => 'Product image uploaded successfully.', 'data' => $product_image]);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $product_id, $image_id)
    {

        try {
            $image_data = array(
                'description' => $request->get('description'),
            );

            if($request->file('image')) {
                $image = $request->file('image');
            $location = str_replace('', '', 'products/' . $product_id . '/' . $image->getClientOriginalName());
            Storage::disk('s3')->put($location, file_get_contents($request->file('image')));
            $image_data['location'] = Storage::url($location);


            // remove previous file 
            $previous_product_image = ProductImage::where('id', $image_id)
            ->where('product_id', $product_id)
            ->first();
            if($previous_product_image) {
                Storage::disk('s3')->delete($previous_product_image->location);
            }
            }

            if ($request->has('feature_image')) {
                $image_data['feature_image'] = $request->get('feature_image') ? 1 : 0;
            }

            ProductImage::where('id', $image_id)->where('product_id', $product_id)->update($image_data);

            $new_product_image = ProductImage::where('id', $image_id)
            ->where('product_id', $product_id)
            ->first();
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update product image.', 'e' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'message' => 'Product image updated successfully.',  'data' => $new_product_image]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($product_id, $image_id)
    {
        try {
            $product_image = ProductImage::where('id', $image_id)->where('product_id', $product_id)->first();

            if($product_image) {
                Storage::disk('s3')->delete($product_image->location);
                $product_image->delete();
            } 
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to delete product image.', 'e' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'message' => 'Product image deleted successfully.']);
    }
}
