<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Declarations SG",
 *     description="Gestion des declarations SG."
 * )
 *
 * @OA\Get(path="/api/declarations/sg", tags={"Declarations SG"}, summary="Lister les declarations", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/declarations/sg", tags={"Declarations SG"}, summary="Creer une declaration", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Declaration creee"))
 * @OA\Get(path="/api/declarations/sg/{id}", tags={"Declarations SG"}, summary="Afficher une declaration", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Declaration"))
 * @OA\Put(path="/api/declarations/sg/{id}", tags={"Declarations SG"}, summary="Mettre a jour une declaration", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Declaration mise a jour"))
 * @OA\Delete(path="/api/declarations/sg/{id}", tags={"Declarations SG"}, summary="Supprimer une declaration", security={{"sanctum":{}}}, @OA\Response(response=204, description="Declaration supprimee"))
 * @OA\Get(path="/api/declarations/sg/{id}/manifeste", tags={"Declarations SG"}, summary="Manifeste lie a une declaration", security={{"sanctum":{}}}, @OA\Response(response=200, description="Manifeste associe"))
 * @OA\Get(path="/api/declarations/sg/{id}/articles", tags={"Declarations SG"}, summary="Articles lies", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste des articles"))
 * @OA\Get(path="/api/declarations/sg/{id}/conteneurs", tags={"Declarations SG"}, summary="Conteneurs lies", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste des conteneurs"))
 * @OA\Post(path="/api/declarations/sg/{id}/validate", tags={"Declarations SG"}, summary="Valider la declaration", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultat de validation"))
 * @OA\Post(path="/api/declarations/sg/{id}/calculate-taxes", tags={"Declarations SG"}, summary="Calculer les taxes", security={{"sanctum":{}}}, @OA\Response(response=200, description="Montants calcules"))
 */
class DeclarationSgEndpoints
{
}


