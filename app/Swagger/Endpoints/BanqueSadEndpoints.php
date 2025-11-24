<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Banque SAD",
 *     description="Gestion des bordereaux SAD."
 * )
 *
 * @OA\Get(path="/api/banque/sad", tags={"Banque SAD"}, summary="Lister les SAD", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/banque/sad", tags={"Banque SAD"}, summary="Creer un SAD", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="SAD cree"))
 * @OA\Get(path="/api/banque/sad/{id}", tags={"Banque SAD"}, summary="Afficher un SAD", security={{"sanctum":{}}}, @OA\Response(response=200, description="SAD detaille"))
 * @OA\Put(path="/api/banque/sad/{id}", tags={"Banque SAD"}, summary="Mettre a jour un SAD", security={{"sanctum":{}}}, @OA\Response(response=200, description="Mise a jour effectuee"))
 * @OA\Delete(path="/api/banque/sad/{id}", tags={"Banque SAD"}, summary="Supprimer un SAD", security={{"sanctum":{}}}, @OA\Response(response=204, description="Suppression effectuee"))
 * @OA\Get(path="/api/banque/sad/{id}/declaration", tags={"Banque SAD"}, summary="Declaration associee", security={{"sanctum":{}}}, @OA\Response(response=200, description="Rattachement declaration"))
 * @OA\Get(path="/api/banque/sad/{id}/manifeste", tags={"Banque SAD"}, summary="Manifeste associe", security={{"sanctum":{}}}, @OA\Response(response=200, description="Rattachement manifeste"))
 * @OA\Post(path="/api/banque/sad/{id}/validate", tags={"Banque SAD"}, summary="Valider un SAD", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultat de validation"))
 */
class BanqueSadEndpoints
{
}


