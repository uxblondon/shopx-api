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
    Route::post('logout', 'Auth\AuthController@logout');
    Route::post('refresh', 'Auth\AuthController@refresh');
    Route::get('me', 'Auth\AuthController@me');

    Route::put('users/{user_id}', 'Auth\UserController@update');
    Route::post('users', 'Auth\UserController@store');
    Route::get('users/{user_id}', 'Auth\UserController@show');
    Route::get('users', 'Auth\UserController@index');

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

    Route::delete('shipping-zones/{zone_id}/rates/{rate_id}', 'Api\ShippingRateController@destroy');
    Route::put('shipping-zones/{zone_id}/rates/{rate_id}', 'Api\ShippingRateController@update');
    Route::get('shipping-zones/{zone_id}/rates/{rate_id}', 'Api\ShippingRateController@show');
    Route::post('shipping-zones/{zone_id}/rates', 'Api\ShippingRateController@store');
    Route::get('shipping-zones/{zone_id}/rates', 'Api\ShippingRateController@index');

    Route::delete('shipping-zones/{zone_id}', 'Api\ShippingZoneController@destroy');
    Route::put('shipping-zones/{zone_id}', 'Api\ShippingZoneController@update');
    Route::get('shipping-zones/{zone_id}', 'Api\ShippingZoneController@show');
    Route::post('shipping-zones', 'Api\ShippingZoneController@store');
    Route::get('shipping-zones', 'Api\ShippingZoneController@index');

    Route::delete('categories/{category_id}', 'Api\CategoryController@destroy');
    Route::put('categories/{category_id}', 'Api\CategoryController@update');
    Route::post('categories', 'Api\CategoryController@store');

    Route::delete('products/{product_id}/variant-types/{variant_type_id}', 'Api\ProductVariantTypeController@destroy');
    Route::put('products/{product_id}/variant-types/{variant_type_id}', 'Api\ProductVariantTypeController@update');
    Route::post('products/{product_id}/variant-types', 'Api\ProductVariantTypeController@store');

    Route::delete('products/{product_id}/variants/{variant_id}', 'Api\ProductVariantController@destroy');
    Route::put('products/{product_id}/variants/{variant_id}', 'Api\ProductVariantController@update');
    Route::post('products/{product_id}/variants', 'Api\ProductVariantController@store');

    Route::delete('products/{product_id}/images/{image_id}', 'Api\ProductImageController@destroy');
    Route::put('products/{product_id}/images/{image_id}', 'Api\ProductImageController@update');
    Route::post('products/{product_id}/images', 'Api\ProductImageController@store');

    Route::delete('products/{product_id}', 'Api\ProductController@destroy');
    Route::put('products/{product_id}', 'Api\ProductController@update');
    Route::post('products', 'Api\ProductController@store');

    Route::delete('customers/{customer_id}/addresses/{address_id}', 'Api\CustomerController@destroy');
    Route::put('customers/{customer_id}/addresses/{address_id}', 'Api\CustomerController@update');
    Route::post('customers/{customer_id}/addresses', 'Api\CustomerController@store');
    Route::get('customers/{customer_id}/addresses/{address_id}', 'Api\CustomerController@show');
    Route::get('customers/{customer_id}/addresses', 'Api\CustomerController@index');

    Route::delete('customers/{customer_id}', 'Api\CustomerController@destroy');
    Route::put('customers/{customer_id}', 'Api\CustomerController@update');
    Route::post('customers', 'Api\CustomerController@store');
    Route::get('customers/{customer_id}', 'Api\CustomerController@show');
    Route::post('customers/filter', 'Api\CustomerController@filter');
    Route::get('customers', 'Api\CustomerController@index');
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