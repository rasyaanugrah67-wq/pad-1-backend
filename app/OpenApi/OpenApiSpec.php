<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="PAD - Website Perlombaan 17 Agustus API",
 *     description="Dokumentasi REST API Website Perlombaan 17 Agustus tingkat RT/RW."
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Masukkan token Sanctum dari hasil login."
 * )
 */
class OpenApiSpec
{
}