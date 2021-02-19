<?php

namespace App\Http\Controllers;


use App\Models\Product;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Shopable",
 *      description="Shopable API",
 *      @OA\Contact(
 *          email="hasan@uxblondon.com"
 *      ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://en.wikipedia.org/wiki/MIT_License"
 *     )
 * )
 */

class ProductController extends Controller
{
    /**
 * @OA\Get(
 *      path="/products",
 *      operationId="getProjectsList",
 *      tags={"Projects"},
 *      summary="Get list of projects",
 *      description="Returns list of projects",
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
        $products = Product::join('product_variant_types', 'products.id', 'product_variant_types.product_id')
        ->get(['products.id', 'products.title', 'product_variant_types.options']);

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
    public function store(Request $request)
    {
        //
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
