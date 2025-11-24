<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Permissions",
 *     description="Gestion des permissions granulaire."
 * )
 *
 * @OA\Get(path="/api/permissions", tags={"Permissions"}, summary="Lister les permissions", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/permissions", tags={"Permissions"}, summary="Creer une permission", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Permission creee"))
 * @OA\Delete(path="/api/permissions/{id}", tags={"Permissions"}, summary="Supprimer une permission", security={{"sanctum":{}}}, @OA\Response(response=204, description="Permission supprimee"))
 * @OA\Post(path="/api/permissions/roles/{roleId}/assign", tags={"Permissions"}, summary="Assigner des permissions a un role", security={{"sanctum":{}}}, @OA\Parameter(name="roleId", in="path", required=true, @OA\Schema(type="integer")), @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Permissions assignees"))
 * @OA\Get(path="/api/permissions/roles/{roleId}", tags={"Permissions"}, summary="Permissions d'un role", security={{"sanctum":{}}}, @OA\Parameter(name="roleId", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Liste des permissions"))
 * @OA\Post(path="/api/permissions/users/{userId}/assign", tags={"Permissions"}, summary="Assigner des permissions a un utilisateur", security={{"sanctum":{}}}, @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")), @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Permissions assignees"))
 * @OA\Get(path="/api/permissions/users/{userId}", tags={"Permissions"}, summary="Permissions d'un utilisateur", security={{"sanctum":{}}}, @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Liste des permissions"))
 */
class PermissionEndpoints
{
}


