<?php

namespace App\Http\Controllers\Api;

use DB;
use Illuminate\Support\Str;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\FilterProductRequest;

/**  @OA\Tag(
 *     name="product",
 *     description="All Endpoints of Product"
 * )
 */
class ProductController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/products",
     *      operationId="GetProductList",
     *      tags={"product"},
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
     *      tags={"product"},
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

        $conditions[] = ['products.status', '=', $status];

        if ($category != '') {
            $conditions[] = ['products.category_id', '=', $category];
        }

        if ($title != '') {
            $conditions[] = ['products.title', 'LIKE', '%' . $title . '%'];
        }

        $sort_by =  'published_at';
        $sort_order =  'desc';
        if ($request->has('sort')) {
            $sort_array = explode(' ', trim($request->get('sort')));
            $sort_by =  isset($sort_array[0]) ? trim($sort_array[0]) : 'published_at';
            $sort_order =  isset($sort_array[1]) ? trim($sort_array[1]) : 'desc';
        }

        $products = Product::join('product_variants', 'products.id', 'product_variants.product_id')
            ->where($conditions)
            ->select(['products.id', 'products.title', 'products.standfirst', 'products.feature_image', DB::raw('min(product_variants.price) as price')])
            ->groupBy('products.id')
            ->orderBy($sort_by, $sort_order)
            ->get();

        return response()->json(['status' => 'success', 'data' => $products, 'c' => $conditions]);
    }


    /**
     * @OA\Post(
     *      path="/api/products",
     *      tags={"product"},
     *      summary="Store new product",
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
    public function store(StoreProductRequest $request)
    {


        $product_data = array(
            'category_id' => $request->get('category_id'),
            'title' => $request->get('title'),
            'slug' => Str::slug($request->get('title')),
            'standfirst' => $request->get('standfirst'),
            'description' => $request->get('description'),
            'tags' => $request->get('tags'),
            'created_by' => auth()->user()->id
        );

        $product = Product::create($product_data);

        return response()->json(['status' => 'success', 'data' => $product]);
    }

    /**
     * @OA\Get(
     *      path="/api/products/{product_id}",
     *      operationId="Products",
     *      tags={"product"},
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
     * @OA\Put(
     *      path="/api/products/{product_id}",
     *      tags={"product"},
     *      summary="Update specified product",
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
    public function update(Request $request, $product_id)
    {
        //
    }

    /**
     * @OA\Delete(
     *      path="/api/products/{product_id}",
     *      tags={"product"},
     *      summary="Delete specified product",
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
    public function destroy($product_id)
    {
        //
    }
}
