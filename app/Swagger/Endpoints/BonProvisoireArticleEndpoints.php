<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Bons Provisoires Articles",
 *     description="Gestion des articles de bons provisoires."
 * )
 *
 * @OA\Get(path="/api/bons-provisoires/articles", tags={"Bons Provisoires Articles"}, summary="Lister les articles de bons", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/bons-provisoires/articles", tags={"Bons Provisoires Articles"}, summary="Creer un article de bon", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Article cree"))
 * @OA\Get(path="/api/bons-provisoires/articles/{id}", tags={"Bons Provisoires Articles"}, summary="Afficher un article de bon", security={{"sanctum":{}}}, @OA\Response(response=200, description="Article detaille"))
 * @OA\Put(path="/api/bons-provisoires/articles/{id}", tags={"Bons Provisoires Articles"}, summary="Mettre a jour un article de bon", security={{"sanctum":{}}}, @OA\Response(response=200, description="Mis a jour"))
 * @OA\Delete(path="/api/bons-provisoires/articles/{id}", tags={"Bons Provisoires Articles"}, summary="Supprimer un article de bon", security={{"sanctum":{}}}, @OA\Response(response=204, description="Supprime"))
 */
class BonProvisoireArticleEndpoints
{
}



