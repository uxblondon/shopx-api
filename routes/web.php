<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Container readiness includes Laravel boot and database connectivity.
Route::get('/healthz', function () {
    \Illuminate\Support\Facades\DB::select('SELECT 1');
    return response()->json(['status' => 'ok']);
});
