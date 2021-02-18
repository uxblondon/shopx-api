<?php

namespace App\Http\Controllers\v1;

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
<<<<<<< HEAD
 *       @OA\Response(
 *          response=400, 
 *          description="Bad request"
 *        ),
=======
 *       @OA\Response(response=400, description="Bad request"),
>>>>>>> cad03ec31320dc3783e5358c2eb87a1cf18685f2
 *       security={
 *           {"api_key_security_example": {}}
 *       }
 *     )
 *
 * Returns list of projects
 */
    public function index()
    {
        return response()->json(['all products' => array()]);
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
