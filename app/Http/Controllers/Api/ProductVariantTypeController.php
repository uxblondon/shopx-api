<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Http\Requests\StoreProductVariantTypeRequest;
use App\Http\Requests\UpdateProductVariantTypeRequest;

use App\Models\ProductVariantType;

class ProductVariantTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreProductVariantTypeRequest $request, $product_id)
    {
        try {
            $product_variant_type_data = array(
                'product_id' => $product_id,
                'variant_no' => $request->get('variant_no'),
                'name' => $request->get('name'),
                'options' => implode(',', $request->get('options'))
            );
            $product_variant_type = ProductVariantType::create($product_variant_type_data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $product_variant_type_data, 'message' => 'Failed to store variant type.']);
        }
        return response()->json(['status' => 'error', 'message' => 'Variant type successfully stored.', 'data' => $product_variant_type]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateProductVariantTypeRequest $request, $product_id, $product_variant_id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($product_id, $product_variant_type_id)
    {
        try {

            $product_variant_type = ProductVariantType::where('id', $product_variant_type_id)
            ->where('product_id', $product_id)->first();

            $variant_data = [];
            if($product_variant_type && $product_variant_type->variant_no === 1) {
                $variant_data['variant_1_id'] = null;
                $variant_data['variant_1_value'] = null;
            } elseif($product_variant_type && $product_variant_type->variant_no === 2) {
                $variant_data['variant_2_id'] = null;
                $variant_data['variant_2_value'] = null;
            } elseif($product_variant_type && $product_variant_type->variant_no === 3) {
                $variant_data['variant_2_id'] = null;
                $variant_data['variant_2_value'] = null;
            }

            ProductVariant::where('product_id', $product_id)->update($variant_data);
            $product_variant_type->delete();
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $product_variant_type_data, 'message' => 'Failed to store variant type.']);
        }
        return response()->json(['status' => 'error', 'message' => 'Variant type successfully stored.', 'data' => $product_variant_type]);
    }
}
