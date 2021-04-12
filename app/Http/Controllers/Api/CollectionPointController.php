<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;
use App\Models\CollectionPoint;

use Illuminate\Http\Request;

class CollectionPointController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $collection_points = CollectionPoint::get()->toArray();

        return response()->json(['status' => 'success', 'data' => $collection_points]);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function activeCollectionPoints()
    {
        $collection_points = CollectionPoint::where('active', 1)->get()->toArray();

        return response()->json(['status' => 'success', 'data' => $collection_points]);
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
    public function store(Request $request)
    {
        $collection_point_data = array(
            'name' => $request->get('name'),
            'address_line_1' => $request->get('address_line_1'),
            'address_line_2' => $request->get('address_line_2'),
            'city' => $request->get('city'),
            'county' => $request->get('county'),
            'postcode' => $request->get('postcode'),
            'country_code' => $request->get('country_code'),
            'note' => $request->get('note'),
            'created_by' => auth()->user()->id
        );

        $collection_point = CollectionPoint::create($collection_point_data);

        return response()->json(['status' => 'success', 'data' => $collection_point]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($collection_point_id)
    {
        $collection_point = CollectionPoint::where('id', $collection_point_id)->first()->toArray();

        return response()->json(['status' => 'success', 'data' => $collection_point]);
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
    public function update(Request $request, $collection_point_id)
    {
        $collection_point_data = array(
            'name' => $request->get('name'),
            'address_line_1' => $request->get('address_line_1'),
            'address_line_2' => $request->get('address_line_2'),
            'city' => $request->get('city'),
            'county' => $request->get('county'),
            'postcode' => $request->get('postcode'),
            'country_code' => $request->get('country_code'),
            'active' => $request->get('active'),
            'note' => $request->get('note'),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => auth()->user()->id
        );

        $update = CollectionPoint::where('id', $collection_point_id)->update($collection_point_data);

        if($update) {
            $collection_point = CollectionPoint::find($collection_point_id);
        }

        return response()->json(['status' => 'success', 'message' => 'Collection point successfully updated.', 'data' => $collection_point]);
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
