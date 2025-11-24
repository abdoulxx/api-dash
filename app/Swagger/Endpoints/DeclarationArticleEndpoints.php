<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Declarations Articles",
 *     description="Gestion des articles de declaration."
 * )
 *
 * @OA\Get(path="/api/declarations/articles", tags={"Declarations Articles"}, summary="Lister les articles", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/declarations/articles", tags={"Declarations Articles"}, summary="Creer un article", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Article cree"))
 * @OA\Get(path="/api/declarations/articles/{id}", tags={"Declarations Articles"}, summary="Afficher un article", security={{"sanctum":{}}}, @OA\Response(response=200, description="Article detaille"))
 * @OA\Put(path="/api/declarations/articles/{id}", tags={"Declarations Articles"}, summary="Mettre a jour un article", security={{"sanctum":{}}}, @OA\Response(response=200, description="Article mis a jour"))
 * @OA\Delete(path="/api/declarations/articles/{id}", tags={"Declarations Articles"}, summary="Supprimer un article", security={{"sanctum":{}}}, @OA\Response(response=204, description="Article supprime"))
 */
class DeclarationArticleEndpoints
{
}


