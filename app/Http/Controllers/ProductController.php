<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;

class ProductController extends Controller
{
    /**
 * @OA\Get(
 *      path="/api/products",
 *      operationId="GetProductList",
 *      tags={"products"},
 *      summary="Get list of products",
 *      description="Returns list of products",
 *      @OA\Response(
 *          response=200,
 *          description="successful operation"
 *       ),
 *       @OA\Response(
 *          response=400,
 *          description="Bad request"
 *        ),
 *       security={
 *           {"api_key_security_example": {}}
 *       }
 *     )
 *
 * Returns list of projects
 */
    public function index()
    {
        $products = Product::join('product_variants', 'products.id', 'product_variants.product_id')
        ->select(['products.id', 'products.title', 'products.standfirst', 'products.feature_image', DB::raw('min(product_variants.price) as price')])
        ->groupBy('products.id')
        ->get();

        return response()->json(['data' => $products]);
    }

    /**
     * Filter products
     */
    public function filter()
    {
        $products = Product::join('product_variant_types', 'products.id', 'product_variant_types.product_id')
        ->get(['products.id', 'products.title', 'product_variant_types.options']);
        return response()->json(['data' => $products]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreProductRequest $request)
    {
        return Product::create($request->all());
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
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
