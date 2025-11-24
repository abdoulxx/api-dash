<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Bons Provisoires SG",
 *     description="Gestion des bons provisoires."
 * )
 *
 * @OA\Get(path="/api/bons-provisoires/sg", tags={"Bons Provisoires SG"}, summary="Lister les bons provisoires", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/bons-provisoires/sg", tags={"Bons Provisoires SG"}, summary="Creer un bon provisoire", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Bon cree"))
 * @OA\Get(path="/api/bons-provisoires/sg/{id}", tags={"Bons Provisoires SG"}, summary="Afficher un bon provisoire", security={{"sanctum":{}}}, @OA\Response(response=200, description="Details"))
 * @OA\Put(path="/api/bons-provisoires/sg/{id}", tags={"Bons Provisoires SG"}, summary="Mettre a jour un bon provisoire", security={{"sanctum":{}}}, @OA\Response(response=200, description="Mis a jour"))
 * @OA\Delete(path="/api/bons-provisoires/sg/{id}", tags={"Bons Provisoires SG"}, summary="Supprimer un bon provisoire", security={{"sanctum":{}}}, @OA\Response(response=204, description="Supprime"))
 * @OA\Get(path="/api/bons-provisoires/sg/{id}/articles", tags={"Bons Provisoires SG"}, summary="Articles lies", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste des articles"))
 * @OA\Post(path="/api/bons-provisoires/sg/{id}/validate", tags={"Bons Provisoires SG"}, summary="Valider un bon provisoire", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultat validation"))
 * @OA\Post(path="/api/bons-provisoires/sg/{id}/expire", tags={"Bons Provisoires SG"}, summary="Forcer l'expiration d'un bon provisoire", security={{"sanctum":{}}}, @OA\Response(response=200, description="Bon expire"))
 */
class BonProvisoireSgEndpoints
{
}


