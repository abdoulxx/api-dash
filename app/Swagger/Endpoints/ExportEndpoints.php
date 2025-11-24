<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Export",
 *     description="Export de donnees metier."
 * )
 *
 * @OA\Post(path="/api/export/excel", tags={"Export"}, summary="Exporter en Excel", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Fichier Excel"))
 * @OA\Post(path="/api/export/pdf", tags={"Export"}, summary="Exporter en PDF", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Fichier PDF"))
 * @OA\Post(path="/api/export/xml", tags={"Export"}, summary="Exporter en XML", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Fichier XML"))
 * @OA\Get(path="/api/export/templates", tags={"Export"}, summary="Recuperer les gabarits d'export", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste des gabarits"))
 */
class ExportEndpoints
{
}


