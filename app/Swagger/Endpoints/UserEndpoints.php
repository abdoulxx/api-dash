<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Utilisateurs",
 *     description="Gestion des utilisateurs applicatifs."
 * )
 *
 * @OA\Get(path="/api/users", tags={"Utilisateurs"}, summary="Lister les utilisateurs", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/users", tags={"Utilisateurs"}, summary="Creer un utilisateur", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Utilisateur cree"))
 * @OA\Get(path="/api/users/{id}", tags={"Utilisateurs"}, summary="Afficher un utilisateur", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Utilisateur"))
 * @OA\Put(path="/api/users/{id}", tags={"Utilisateurs"}, summary="Mettre a jour un utilisateur", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Utilisateur mis a jour"))
 * @OA\Delete(path="/api/users/{id}", tags={"Utilisateurs"}, summary="Supprimer un utilisateur", security={{"sanctum":{}}}, @OA\Response(response=204, description="Utilisateur supprime"))
 */
class UserEndpoints
{
}



