<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="FDI Comparaison",
 *     description="Table de comparaison technique des FDI."
 * )
 *
 * @OA\Get(path="/api/fdi/rech-comp", tags={"FDI Comparaison"}, summary="Lister les entrees de comparaison FDI", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/fdi/rech-comp", tags={"FDI Comparaison"}, summary="Creer une entree de comparaison", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Entree creee"))
 * @OA\Get(path="/api/fdi/rech-comp/{id}", tags={"FDI Comparaison"}, summary="Afficher une entree de comparaison", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Entree detaillee"))
 * @OA\Put(path="/api/fdi/rech-comp/{id}", tags={"FDI Comparaison"}, summary="Mettre a jour une entree de comparaison", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Entree mise a jour"))
 * @OA\Delete(path="/api/fdi/rech-comp/{id}", tags={"FDI Comparaison"}, summary="Supprimer une entree de comparaison", security={{"sanctum":{}}}, @OA\Response(response=200, description="Entree supprimee"))
 */
class FdiRechCompEndpoints
{
}


