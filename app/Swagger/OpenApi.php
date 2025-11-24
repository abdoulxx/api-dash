<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="S360°",
 *     version="1.0.0",
 *     description="Documentation interactive de S360°, couvrant les modules migrés depuis S360.",
 *     @OA\Contact(
 *         email="support@s360.local",
 *         name="Équipe S360°"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Instance actuelle (définie via L5_SWAGGER_CONST_HOST)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *    type="http",
 *    scheme="bearer",
 *    bearerFormat="Sanctum Token",
 *    description="Utiliser un token d'accès généré via Sanctum."
 * )
 */
class OpenApi
{
    // Fichier de configuration principal pour les annotations OpenAPI.
}

