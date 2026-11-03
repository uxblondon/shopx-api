<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'ShopX API', description: 'E-commerce API')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
class OpenApi
{
}
