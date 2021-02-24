<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\FilterCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;

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
        $categories = Category::all();

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
            'tags' => $request->get('tags'),
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
    public function show($category_id)
    {
        $category = Category::find($category_id);
        $category->products = Product::where('category_id', $category_id)->get();

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
    public function update(UpdateCategoryRequest $request, $product_id)
    {

        $category_data = array();

        if($request->has('category_id')) {
            $category_data['category_id'] = $request->get('category_id');
        }

        if($request->has('title')) {
            $category_data['title'] = $request->get('title');
        }

        if($request->has('standfirst')) {
            $category_data['standfirst'] = $request->get('standfirst');
        }

        if($request->has('description')) {
            $category_data['description'] = $request->get('description');
        }

        if($request->has('feature_image')) {
            $category_data['feature_image'] = $request->get('feature_image');
        }

        if($request->has('status')) {
            $category_data['status'] = $request->get('status');
        }

        if($request->has('meta_description')) {
            $category_data['meta_description'] = $request->get('meta_description');
        }

        if($request->has('meta_keywords')) {
            $category_data['meta_keywords'] = $request->get('meta_keywords');
        }

        if(count($category_data)>0){
            $update = Category::where('id', $category_id)->update($category_data);
            if($update) {
                $category = Category::find($category_id);
                return response()->json(['status' => 'success', 'data' => $category]);
            }

            return response()->json(['status' => 'error', 'message' => 'Failed to update product.']);
        }

        return response()->json(['status' => 'error', 'message' => 'No data provided', 'd' => $request->all()]);
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

        if($product) {
            $product->delete();
            return response()->json(['status' => 'success', 'message' => 'Product successfully deleted.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Product not exist.']);
    }
}
