<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\FilterCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Storage;

/**  @OA\Tag(
 *     name="category",
 *     description="All Endpoints of Category"
 * )
 */
class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/categories",
     *      tags={"category"},
     *      summary="Get list of all categories",
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
        $categories = Category::leftJoin('product_categories', 'categories.id', 'product_categories.category_id')
            ->select(['categories.id', 'categories.title', 'categories.standfirst', DB::raw('count(product_categories.id) as no_of_products'), 'categories.status'])
            ->groupBy('categories.id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }


    /**
     * @OA\Get(
     *      path="/api/categories",
     *      tags={"category"},
     *      summary="Get list of all categories",
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
    public function unlisted()
    {
        $categories = Category::leftJoin('product_categories', 'categories.id', 'product_categories.category_id')
            ->select(['categories.id', 'categories.title', 'categories.standfirst', DB::raw('count(product_categories.id) as no_of_products'), 'categories.status'])
            ->where('categories.status', 'unlisted')
            ->groupBy('categories.id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    /**
     * @OA\Get(
     *      path="/api/categories",
     *      tags={"category"},
     *      summary="Get list of all categories",
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
    public function publishedList()
    {
        $categories = Category::leftJoin('product_categories', 'categories.id', 'product_categories.category_id')
            ->select(['categories.id', 'categories.title', 'categories.standfirst', DB::raw('count(product_categories.id) as no_of_products'), 'categories.status'])
            ->where('categories.status', 'published')
            ->groupBy('categories.id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }


    /**
     * @OA\Post(
     *      path="/api/categories/filter",
     *      tags={"category"},
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
    public function filter(FilterCategoryRequest $request)
    {
        $title = trim($request->get('title'));
        $status = $request->has('status') ? trim($request->get('status')) : 'published';

        $conditions[] = ['categories.status', '=', $status];

        if ($title != '') {
            $conditions[] = ['categories.title', 'LIKE', '%' . $title . '%'];
        }

        $sort_by =  'published_at';
        $sort_order =  'desc';
        if ($request->has('sort')) {
            $sort_array = explode(' ', trim($request->get('sort')));
            $sort_by =  isset($sort_array[0]) ? trim($sort_array[0]) : 'published_at';
            $sort_order =  isset($sort_array[1]) ? trim($sort_array[1]) : 'desc';
        }

        $categories = Category::where($conditions)
            ->orderBy($sort_by, $sort_order)
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    /**
     * @OA\Post(
     *      path="/api/categories",
     *      tags={"category"},
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
    public function store(StoreCategoryRequest $request)
    {
        $category_data = array(
            'title' => $request->get('title'),
            'slug' => Str::slug($request->get('title')),
            'standfirst' => $request->get('standfirst'),
            'description' => $request->get('description'),
            'created_by' => auth()->user()->id
        );

        $category = Category::create($category_data);

        return response()->json(['status' => 'success', 'data' => $category]);
    }

    /**
     * @OA\Get(
     *      path="/api/categories/{product_id}",
     *      operationId="Products",
     *      tags={"category"},
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
    public function publishedInfo($category_id)
    {
        $product_data = [
            'products.id',
            'products.title',
            'products.standfirst',
            DB::raw('count(product_variants.id) as no_of_variants'),
            DB::raw('min(product_variants.price) as price_from'),
            DB::raw('sum(product_variants.stock) as stock'),
            'product_images.description as feature_image_description',
            'product_images.location as feature_image_location',
            'products.status',
        ];

      //  $category = Category::where('categories.status', 'published')->where('categories.id', $category_id)->first();

        $category = Category::whereIn('categories.status', ['published', 'unlisted'])->where('id', $category_id)->first();

        if ($category) {
            $category->products = Product::Join('product_categories', function ($join) use ($category_id) {
                $join->on('product_categories.product_id', 'products.id')
                    ->where('product_categories.category_id', $category_id);
            })->leftJoin('product_variants', function ($join) {
                $join->on('products.id', 'product_variants.product_id')
                    ->whereNull('product_variants.deleted_at');
            })->leftJoin('product_images', function ($join) {
                $join->on('product_images.product_id', 'products.id')
                    ->where('product_images.feature_image', 1);
            })->select($product_data)
                ->where('products.status', 'published')
                ->orderBy('products.title', 'asc')
                ->groupBy('products.id')
                ->groupBy('product_images.id')
                ->get();
        }

        return response()->json(['status' => 'success', 'data' => $category]);
    }

    /**
     * @OA\Get(
     *      path="/api/categories/{product_id}",
     *      operationId="Products",
     *      tags={"category"},
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
    public function show($category_id)
    {
        $product_data = [
            'products.id',
            'products.title',
            'products.standfirst',
            DB::raw('count(product_variants.id) as no_of_variants'),
            DB::raw('min(product_variants.price) as price_from'),
            DB::raw('sum(product_variants.stock) as stock'),
            'product_images.description as feature_image_description',
            'product_images.location as feature_image_location',
            'products.status',
        ];

        $category = Category::find($category_id);

        if ($category) {
            $category->products = Product::Join('product_categories', function ($join) use ($category_id) {
                $join->on('product_categories.product_id', 'products.id')
                    ->where('product_categories.category_id', $category_id);
            })->leftJoin('product_variants', function ($join) {
                $join->on('products.id', 'product_variants.product_id')
                    ->whereNull('product_variants.deleted_at');
            })->leftJoin('product_images', function ($join) {
                $join->on('product_images.product_id', 'products.id')
                    ->where('product_images.feature_image', 1);
            })->select($product_data)
                ->orderBy('products.title', 'asc')
                ->groupBy('products.id')
                ->groupBy('product_images.id')
                ->get();
        }

        return response()->json(['status' => 'success', 'data' => $category]);
    }

    /**
     * @OA\Put(
     *      path="/api/categories/{product_id}",
     *      tags={"category"},
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
    public function update(UpdateCategoryRequest $request, $category_id)
    {
        try {
            $category_data = array();
            $category_data['updated_at'] = date('Y-m-d H:i:s');
            $category_data['updated_by'] = auth()->user()->id;

            if ($request->has('title') && $request->get('title') != '') {
                $category_data['title'] = $request->get('title');
            }

            if ($request->has('standfirst') && $request->get('standfirst') != '') {
                $category_data['standfirst'] = $request->get('standfirst');
            }

            if ($request->has('description') && $request->get('description') != '') {
                $category_data['description'] = $request->get('description');
            }

            if ($request->has('status') && $request->get('status') != '') {

                if ($request->get('status') === 'published') {
                    $category_data['status'] = 'published';
                    $category_data['published_at'] = date('Y-m-d H:i:s');
                }

                $category_data['status'] = $request->get('status');
                $category_data['published_at'] = NULL;
            }

            if ($request->has('meta_description') && $request->get('meta_description') != '') {
                $category_data['meta_description'] = $request->get('meta_description');
            }

            if ($request->has('meta_keywords') && $request->get('meta_keywords') != '') {
                $category_data['meta_keywords'] = $request->get('meta_keywords');
            }


            Category::where('id', $category_id)->update($category_data);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to update product category.']);
        }

        return response()->json(['status' => 'success', 'message' => 'Product category successfully updated.',]);
    }

    /**
     * @OA\Delete(
     *      path="/api/categories/{product_id}",
     *      tags={"category"},
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
        $product = Category::find($product_id);

        if ($product) {
            $product->delete();
            return response()->json(['status' => 'success', 'message' => 'Product successfully deleted.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Product not exist.']);
    }
}

