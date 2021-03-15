<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use DB;
use Illuminate\Http\Request;
use App\Models\User;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $customers = User::leftJoin('orders', 'orders.user_id', 'users.id')
        ->leftJoin('customer_addresses', function($join){
            $join->on('customer_addresses.user_id', 'users.id')
            ->where('customer_addresses.default', 1);
        })->where('users.admin', 0)
        ->select([
            'users.id',
            'users.name',
            'users.email',
            'customer_addresses.phone',
            DB::raw('count(DISTINCT orders.id) as no_of_orders')
        ])
        ->groupBy('users.id')
        ->groupBy('customer_addresses.phone')
        ->get();

        return response()->json(['status' => 'success', 'data' => $customers]);
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
    public function show($customer_id)
    {
        $customer = User::find($customer_id);

        return response()->json(['status' => 'success', 'data' => $customer]);
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
