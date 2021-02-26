<?php

namespace App\Http\Controllers\Api;

use DB;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductVariantType;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\FilterProductRequest;
use App\Http\Requests\UpdateProductRequest;

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
        $product = Product::find($product_id);

        if ($product) {

            $product->price_from = ProductVariant::where('product_id', $product_id)->min('price');
            $variants['types'] = ProductVariantType::where('product_id', $product_id)
                ->get(['id', 'name', 'options'])
                ->toArray();

            $variants['values'] = ProductVariant::leftJoin('product_variant_options', 'product_variants.id', 'product_variant_options.product_variant_id')
                ->leftJoin('product_variant_types as variant_1', 'variant_1.id', 'product_variant_options.variant_1_id')
                ->leftJoin('product_variant_types as variant_2', 'variant_2.id', 'product_variant_options.variant_2_id')
                ->leftJoin('product_variant_types as variant_3', 'variant_3.id', 'product_variant_options.variant_3_id')
                ->where('product_variants.product_id', $product_id)
                ->get([
                    'product_variants.id',
                    'product_variants.price',
                    'product_variants.weight',
                    'variant_1.name as variant_1_name',
                    'variant_1.options as variant_1_options',
                    'product_variant_options.variant_1_value as variant_1_value',
                    'variant_2.name as variant_2_name',
                    'variant_2.options as variant_2_options',
                    'product_variant_options.variant_2_value as variant_2_value',
                    'variant_2.name as variant_3_name',
                    'variant_3.options as variant_3_options',
                    'product_variant_options.variant_3_value as variant_3_value',
                ])
                ->toArray();

            $product->variants = $variants;

            return response()->json(['status' => 'success', 'data' => $product]);
        }

        return response()->json(['status' => 'error', 'message' => 'Product not found.']);
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
    public function update(UpdateProductRequest $request, $product_id)
    {

        $product_data = array();

        if ($request->has('category_id')) {
            $product_data['category_id'] = $request->get('category_id');
        }

        if ($request->has('title')) {
            $product_data['title'] = $request->get('title');
        }

        if ($request->has('standfirst')) {
            $product_data['standfirst'] = $request->get('standfirst');
        }

        if ($request->has('description')) {
            $product_data['description'] = $request->get('description');
        }

        if ($request->has('feature_image')) {
            $product_data['feature_image'] = $request->get('feature_image');
        }

        if ($request->has('tags')) {
            $product_data['tags'] = $request->get('tags');
        }

        if ($request->has('status')) {
            $product_data['status'] = $request->get('status');
        }

        if ($request->has('meta_description')) {
            $product_data['meta_description'] = $request->get('meta_description');
        }

        if ($request->has('meta_keywords')) {
            $product_data['meta_keywords'] = $request->get('meta_keywords');
        }

        if (count($product_data) > 0) {
            $update = Product::where('id', $product_id)->update($product_data);
            if ($update) {
                $product = Product::find($product_id);
                return response()->json(['status' => 'success', 'data' => $product]);
            }

            return response()->json(['status' => 'error', 'message' => 'Failed to update product.']);
        }

        return response()->json(['status' => 'error', 'message' => 'No data provided', 'd' => $request->all()]);
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
        $product = Product::find($product_id);

        if ($product) {
            $product->delete();
            return response()->json(['status' => 'success', 'message' => 'Product successfully deleted.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Product not exist.']);
    }
}
