<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::where('admin', 1)
        ->orderBy('disabled', 'asc')
        ->orderBy('name', 'asc')
        ->get(['id', 'name', 'email', 'disabled']);

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUserRequest $request)
    {
        $user_data = array(
            'admin' => 1,
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Hash::make($request->get('password')),
        );

        $user = User::create($user_data);

        if($user) {
            return response()->json(['status' => 'success', 'data' => $user]);
        }
        return response()->json(['status' => 'error', 'message' => 'Failed to create user.']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($user_id)
    {
        $user = User::where('id', $user_id)->where('admin', 1)->first();

        if($user) {
            return response()->json(['status' => 'success', 'data' => $user]);
        }
        return response()->json(['status' => 'error', 'message' => 'User not found.']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUserRequest $request, $user_id)
    {
        $user_data = array();

        if ($request->has('name')) {
            $user_data['name'] = $request->get('name');
        }

        if ($request->has('email')) {
            $user_data['email'] = $request->get('email');
        }

        if ($request->has('password')) {
            $user_data['password'] = Hash::make($request->get('password'));
        }

        if ($request->has('disabled')) {
            $user_data['disabled'] = $request->get('disabled');
        }

        if(count($user_data) > 0) {
            $update = User::where('id', $user_id)->where('admin', 1)->update($user_data);

        if($update) {
            $user = User::find($user_id);
            return response()->json(['status' => 'success', 'message' => 'User details successfully updated.', 'data' => $user]);
        }
        return response()->json(['status' => 'error', 'message' => 'Failed to create user.']);

        }

        return response()->json(['status' => 'error', 'message' => 'No data provided.']);
    }
}
