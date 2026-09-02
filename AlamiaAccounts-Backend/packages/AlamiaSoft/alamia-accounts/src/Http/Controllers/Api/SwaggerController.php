<?php

/**
 * @OA\Info(
 *     title="AlamiaAccounts API",
 *     version="1.0.0",
 *     description="API for multi-company accounting application",
 *     @OA\Contact(
 *         email="support@alamiasoft.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\PathItem(
 *     path="/",
 *     @OA\Get(
 *         summary="API Health Check",
 *         tags={"General"},
 *         @OA\Response(
 *             response=200,
 *             description="API is running"
 *         )
 *     )
 * )
 */

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;

class SwaggerController extends Controller
{
    // This file contains Swagger documentation annotations only
}
