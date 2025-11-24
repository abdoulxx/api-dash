<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="Gestion des roles et permissions."
 * )
 *
 * @OA\Get(path="/api/roles", tags={"Roles"}, summary="Lister les roles", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste des roles"))
 * @OA\Post(path="/api/roles", tags={"Roles"}, summary="Creer un role", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Role cree"))
 * @OA\Get(path="/api/roles/{id}", tags={"Roles"}, summary="Afficher un role", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Role"))
 * @OA\Put(path="/api/roles/{id}", tags={"Roles"}, summary="Mettre a jour un role", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Role mis a jour"))
 * @OA\Delete(path="/api/roles/{id}", tags={"Roles"}, summary="Supprimer un role", security={{"sanctum":{}}}, @OA\Response(response=204, description="Role supprime"))
 */
class RoleEndpoints
{
}


