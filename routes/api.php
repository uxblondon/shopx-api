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
    Route::get('available-products', 'Api\ProductController@available');


    Route::post('orders', 'Api\OrderController@store');



    Route::post('delivery-options', 'Api\DeliveryOptionController@options');
    Route::post('collection-options', 'Api\CollectionOptionController@options');

    Route::get('shipping-zones/shippable-countries', 'Api\ShippingZoneController@shippableCountries');
    Route::get('active-collection-points', 'Api\CollectionPointController@activeCollectionPoints');
    Route::get('available-store-addresses', 'Api\StoreAddressController@available');

    Route::post('stripe/secret', 'Api\StripeController@secret');

    Route::get('orders/sequence', 'Api\OrderController@sequence');

   
    
});

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

    Route::delete('store-addresses/{zone_id}', 'Api\StoreAddressController@destroy');
    Route::put('store-addresses/{zone_id}', 'Api\StoreAddressController@update');
    Route::get('store-addresses/{zone_id}', 'Api\StoreAddressController@show');
    Route::post('store-addresses', 'Api\StoreAddressController@store');
    Route::get('collection-addresses', 'Api\StoreAddressController@collectionAddresses');
    Route::get('store-addresses', 'Api\StoreAddressController@index');

    Route::delete('collection-points/{collection_point_id}/rates/{rate_id}', 'Api\CollectionRateController@destroy');
    Route::put('collection-points/{collection_point_id}/rates/{rate_id}', 'Api\CollectionRateController@update');
    Route::get('collection-points/{collection_point_id}/rates/{rate_id}', 'Api\CollectionRateController@show');
    Route::post('collection-points/{collection_point_id}/rates', 'Api\CollectionRateController@store');
    Route::get('collection-points/{collection_point_id}/rates', 'Api\CollectionRateController@index');


    Route::delete('collection-points/{collection_point_id}', 'Api\CollectionPointController@destroy');
    Route::put('collection-points/{collection_point_id}', 'Api\CollectionPointController@update');
    Route::get('collection-points/{collection_point_id}', 'Api\CollectionPointController@show');
    Route::post('collection-points', 'Api\CollectionPointController@store');
    Route::get('collection-points', 'Api\CollectionPointController@index');

    Route::delete('shipping-zones/{zone_id}/rates/{rate_id}', 'Api\ShippingRateController@destroy');
    Route::put('shipping-zones/{zone_id}/rates/{rate_id}', 'Api\ShippingRateController@update');
    Route::get('shipping-zones/{zone_id}/rates/{rate_id}', 'Api\ShippingRateController@show');
    Route::post('shipping-zones/{zone_id}/rates', 'Api\ShippingRateController@store');
    Route::get('shipping-zones/{zone_id}/rates', 'Api\ShippingRateController@index');

    Route::put('shipping-zones/{zone_id}/products', 'Api\ShippingZoneController@manageProducts');
    Route::get('shipping-zones/{zone_id}/products', 'Api\ShippingZoneController@products');

    Route::put('shipping-zones/{zone_id}/countries', 'Api\ShippingZoneController@manageShippingCountries');
    Route::get('shipping-zones/{zone_id}/countries', 'Api\ShippingZoneController@countries');

    Route::put('shipping-options/{option_id}/products', 'Api\ShippingOptionController@manageProducts');
    Route::get('shipping-options/{option_id}/products', 'Api\ShippingOptionController@products');

    Route::delete('shipping-options/{package_size_id}', 'Api\ShippingOptionController@destroy');
    Route::put('shipping-options/{package_size_id}', 'Api\ShippingOptionController@update');
    Route::get('shipping-options/{package_size_id}', 'Api\ShippingOptionController@show');
    Route::post('shipping-options', 'Api\ShippingOptionController@store');
    Route::get('available-shipping-options', 'Api\ShippingOptionController@available');
    Route::get('shipping-options', 'Api\ShippingOptionController@index');

    Route::delete('shipping-package-sizes/{package_size_id}', 'Api\ShippingPackageSizeController@destroy');
    Route::put('shipping-package-sizes/{package_size_id}', 'Api\ShippingPackageSizeController@update');
    Route::get('shipping-package-sizes/{package_size_id}', 'Api\ShippingPackageSizeController@show');
    Route::post('shipping-package-sizes', 'Api\ShippingPackageSizeController@store');
    Route::get('available-shipping-package-sizes', 'Api\ShippingPackageSizeController@available');
    Route::get('shipping-package-sizes', 'Api\ShippingPackageSizeController@index');
    
    
    Route::delete('shipping-zones/{zone_id}', 'Api\ShippingZoneController@destroy');
    Route::put('shipping-zones/{zone_id}', 'Api\ShippingZoneController@update');
    Route::get('shipping-zones/{zone_id}', 'Api\ShippingZoneController@show');
    Route::post('shipping-zones', 'Api\ShippingZoneController@store');
    Route::get('available-shipping-zones', 'Api\ShippingZoneController@available');
    Route::get('shipping-zones', 'Api\ShippingZoneController@index');

    Route::delete('categories/{category_id}', 'Api\CategoryController@destroy');
    Route::put('categories/{category_id}', 'Api\CategoryController@update');
    Route::post('categories', 'Api\CategoryController@store');

    Route::put('products/{product_id}/shipping', 'Api\ProductController@manageShipping');
    Route::get('products/{product_id}/shipping', 'Api\ProductController@shipping');

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


    Route::delete('orders/{order_id}', 'Api\OrderController@destroy');
    Route::put('orders/{order_id}', 'Api\OrderController@update');
  //  Route::post('orders', 'Api\OrderController@store');
    Route::get('orders/{order_id}', 'Api\OrderController@show');
    Route::post('orders/filter', 'Api\OrderController@filter');
    Route::get('orders', 'Api\OrderController@index');


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

