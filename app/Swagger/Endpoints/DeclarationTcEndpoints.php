<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Declarations TC",
 *     description="Gestion des declarations conteneurs."
 * )
 *
 * @OA\Get(path="/api/declarations/tc", tags={"Declarations TC"}, summary="Lister les enregistrements TC", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/declarations/tc", tags={"Declarations TC"}, summary="Creer un enregistrement TC", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Creation reussie"))
 * @OA\Get(path="/api/declarations/tc/{id}", tags={"Declarations TC"}, summary="Afficher un enregistrement TC", security={{"sanctum":{}}}, @OA\Response(response=200, description="Enregistrement detaille"))
 * @OA\Put(path="/api/declarations/tc/{id}", tags={"Declarations TC"}, summary="Mettre a jour un enregistrement TC", security={{"sanctum":{}}}, @OA\Response(response=200, description="Mise a jour effectuee"))
 * @OA\Delete(path="/api/declarations/tc/{id}", tags={"Declarations TC"}, summary="Supprimer un enregistrement TC", security={{"sanctum":{}}}, @OA\Response(response=204, description="Suppression effectuee"))
 */
class DeclarationTcEndpoints
{
}


