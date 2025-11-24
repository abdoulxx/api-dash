<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="FDI Articles",
 *     description="Gestion des articles lies aux FDI."
 * )
 *
 * @OA\Get(
 *     path="/api/fdi/articles",
 *     tags={"FDI Articles"},
 *     summary="Lister les articles de FDI",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Liste paginee")
 * )
 * @OA\Post(path="/api/fdi/articles", tags={"FDI Articles"}, summary="Creer un article de FDI", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Article cree"))
 * @OA\Get(path="/api/fdi/articles/{id}", tags={"FDI Articles"}, summary="Afficher un article de FDI", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Article detaille"))
 * @OA\Put(path="/api/fdi/articles/{id}", tags={"FDI Articles"}, summary="Mettre a jour un article de FDI", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Article mis a jour"))
 * @OA\Delete(path="/api/fdi/articles/{id}", tags={"FDI Articles"}, summary="Supprimer un article de FDI", security={{"sanctum":{}}}, @OA\Response(response=200, description="Article supprime"))
 */
class FdiArticleEndpoints
{
}


