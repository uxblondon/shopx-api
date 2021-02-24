<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/* auth routes */
Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'auth',
], function () {
    Route::post('logout', 'AuthController@logout');
    Route::post('refresh', 'AuthController@refresh');
    Route::post('me', 'Auth\AuthController@me');
});

/* auth routes */
Route::group([
    'middleware' => 'api',
    'prefix' => 'auth',
], function () {
    Route::post('login', 'Auth\AuthController@login');
});

/* authenticated routes */
Route::group([
    'middleware' => 'auth:api',
], function () {
    Route::delete('categories/{category_id}', 'Api\CategoryController@destroy');
    Route::put('categories/{category_id}', 'Api\CategoryController@update');
    Route::post('categories', 'Api\CategoryController@store');

    Route::delete('products/{product_id}', 'Api\ProductController@destroy');
    Route::put('products/{product_id}', 'Api\ProductController@update');
    Route::post('products', 'Api\ProductController@store');
});

/* public routes */
Route::group([
    'middleware' => 'api',
], function () {
    Route::get('categories/{category_id}', 'Api\CategoryController@show');
    Route::post('categories/filter', 'Api\CategoryController@filter');
    Route::get('categories', 'Api\CategoryController@index');

    Route::get('products/{product_id}', 'Api\ProductController@show');
    Route::post('products/filter', 'Api\ProductController@filter');
    Route::get('products', 'Api\ProductController@index');
});