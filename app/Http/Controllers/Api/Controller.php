<?php

namespace App\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="ShopX API",
 *      description="A simple yet smart RESTful API for e-commerce solution",
 *      @OA\Contact(
 *          email="hasan@uxblondon.com"
 *      ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://en.wikipedia.org/wiki/MIT_License"
 *     )
 * )
 */

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
