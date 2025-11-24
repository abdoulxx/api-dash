<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Banque TVF Comp 2",
 *     description="Donnees complementaires TVF niveau 2."
 * )
 *
 * @OA\Get(path="/api/banque/tvf-comp-2", tags={"Banque TVF Comp 2"}, summary="Lister les complements", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/banque/tvf-comp-2", tags={"Banque TVF Comp 2"}, summary="Creer un complement", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Enregistrement cree"))
 * @OA\Get(path="/api/banque/tvf-comp-2/{id}", tags={"Banque TVF Comp 2"}, summary="Afficher un complement", security={{"sanctum":{}}}, @OA\Response(response=200, description="Details"))
 * @OA\Put(path="/api/banque/tvf-comp-2/{id}", tags={"Banque TVF Comp 2"}, summary="Mettre a jour un complement", security={{"sanctum":{}}}, @OA\Response(response=200, description="Mis a jour"))
 * @OA\Delete(path="/api/banque/tvf-comp-2/{id}", tags={"Banque TVF Comp 2"}, summary="Supprimer un complement", security={{"sanctum":{}}}, @OA\Response(response=204, description="Supprime"))
 */
class BanqueTvfComp2Endpoints
{
}


