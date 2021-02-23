<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\FilterProductRequest;

/**  @OA\Tag(
    *     name="Products",
    *     description="API Endpoints of Products"
    * )
    */
class ProductController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/products",
     *      operationId="GetProductList",
     *      tags={"Products"},
     *      summary="Get list of all products",
     *      description="Returns list of products",
     *      @OA\Response(
     *          response=200,
     *          description="Successful response"
     *       ),
     *       @OA\Response(
     *          response=400,
     *          description="Bad request"
     *        )
     *     )
     */
    public function index()
    {
        $products = Product::join('product_variants', 'products.id', 'product_variants.product_id')
        ->select(['products.id', 'products.title', 'products.standfirst', 'products.feature_image', DB::raw('min(product_variants.price) as price')])
        ->groupBy('products.id')
        ->get();
            
        return response()->json(['status' => 'success', 'data' => $products]);
    }

    /**
     * @OA\Post(
     *      path="/api/products/filter",
     *      tags={"Products"},
     *      summary="Get list of filtered products",
     *      @OA\Parameter(
     *          name="id",
     *          description="Product id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful response"
     *       ),
     *       @OA\Response(
     *          response=400,
     *          description="Bad request"
     *        )
     *     )
     */
    public function filter(FilterProductRequest $request)
    {
        $category = trim($request->get('category_id'));
        $title = trim($request->get('title'));
        $status = $request->has('status') ? trim($request->get('status')) : 'published';
        
        if ($category != '') {
            $conditions[] = ['products.category_id', '=', $category];
        }

        if ($title != '') {
            $conditions[] = ['products.title', 'LIKE', '%'.$title.'%'];
        }

        $conditions[] = ['products.status', '=', $status];

        $sort_by =  'date';
        $sort =  'desc';
        if ($request->has('sort')) {

$sort_array = explode(' ', trim($request->get('sort')));

$sort_by =  isset($sort_array[0]) ? trim($sort_array[0]) : 'published_at';
        $sort =  isset($sort_array[1]) ? trim($sort_array[1]) : 'desc';


        }
        

        // $fractal = new Manager();

        // $paginator = Activity::join('organisations', 'organisations.id', 'activities.organisation_id')
        //             ->where($conditions)
        //             ->orderBy($this->order_by, $this->order)
        //             ->select(['activities.*'])
        //             ->paginate(20);


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
     * @OA\Get(
     *      path="/api/products/{product_id}",
     *      operationId="Products",
     *      tags={"Products"},
     *      summary="Get list of filtered products",
     *      description="Get list of filtered products",
     *      @OA\Parameter(
     *          name="product_id",
     *          description="Product id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful response"
     *       ),
     *     )
     */
    public function show($product_id)
    {
        return Product::find($product_id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $product_id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($product_id)
    {
        //
    }
}
