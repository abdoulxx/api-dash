<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Import",
 *     description="Import de jeux de donnees."
 * )
 *
 * @OA\Post(path="/api/import/excel", tags={"Import"}, summary="Importer un fichier Excel", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="file", type="string", format="binary"))), @OA\Response(response=200, description="Import en file d'attente"))
 * @OA\Post(path="/api/import/csv", tags={"Import"}, summary="Importer un fichier CSV", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="file", type="string", format="binary"))), @OA\Response(response=200, description="Import en file d'attente"))
 */
class ImportEndpoints
{
}


