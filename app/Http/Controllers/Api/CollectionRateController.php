<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class CollectionRateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($collection_point_id)
    {
        try {
            
            $rates = CollectionRate::where('collection_rates.collection_point_id', $collection_point_id)
            ->orderBy('collection_rates.cost')
            ->get()
            ->toArray();

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'data' => $rates]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $collection_point_id)
    {
        try {
            $collection_rate_data = array(
                'collection_point_id' => $collection_point_id,
                'speed' => $request->get('speed'),
                'cost' => $request->get('cost'),
                'available' => $request->get('available'),
                'note' => $request->get('note'),
                'created_by' => auth()->user()->id
            );

            $rate = CollectionRate::create($collection_rate_data);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to store collection rate.']);
        }

        return response()->json(['status' => 'success', 'message' => 'Collection rate successfully stored.', 'data' => $rate]);
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
    public function update(Request $request, $collection_point_id, $collection_rate_id)
    {
        try {
                
                $collection_rate_data = array(
                    'speed' => $request->get('speed'),
                    'cost' => $request->get('cost'),
                    'available' => $request->get('available'),
                    'note' => $request->get('note'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => auth()->user()->id
                );
    
               
        
                CollectionRate::where('id', $collection_rate_id)->update($collection_rate_data);
    
                $rate = CollectionRate::where('id', $collection_rate_id)->first();

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to update collection rate.']);
        }

        return response()->json(['status' => 'success', 'message' => 'Collection rate successfully updated.', 'data' => $rate]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($collection_point_id, $collection_rate_id)
    {
        try {
            CollectionRate::where('id', $collection_rate_id)->where('collection_point_id', $collection_point_id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => auth()->user()->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'e' => $e->getMessage(), 'message' => 'Failed to delete collection rate.']);
        }
        return response()->json(['status' => 'success', 'message' => 'Collection rate successfully deleted.']);
    }
}
