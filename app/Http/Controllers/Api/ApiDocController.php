<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 May 2025 00:10:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Aiku API Documentation",
 *     version="1.0.0",
 *     description="API documentation for the Aiku application",
 *     @OA\Contact(
 *         email="raul@inikoo.com",
 *         name="Aiku Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Enter token in format (Bearer <token>)"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for user authentication"
 * )
 *
 * @OA\Tag(
 *     name="Retina",
 *     description="Retina API endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Aurora",
 *     description="Aurora integration API endpoints"
 * )
 */
class ApiDocController extends Controller
{
    /**
     * Display the API documentation.
     */
    public function index()
    {
        return view('vendor.l5-swagger.index');
    }
}
