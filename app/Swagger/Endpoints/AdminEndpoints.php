<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Administrateurs",
 *     description="Gestion des comptes administrateurs."
 * )
 *
 * @OA\Get(path="/api/admins", tags={"Administrateurs"}, summary="Lister les administrateurs", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/admins", tags={"Administrateurs"}, summary="Creer un administrateur", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Administrateur cree"))
 * @OA\Get(path="/api/admins/{id}", tags={"Administrateurs"}, summary="Afficher un administrateur", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Administrateur"))
 * @OA\Put(path="/api/admins/{id}", tags={"Administrateurs"}, summary="Mettre a jour un administrateur", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=200, description="Administrateur mis a jour"))
 * @OA\Delete(path="/api/admins/{id}", tags={"Administrateurs"}, summary="Supprimer un administrateur", security={{"sanctum":{}}}, @OA\Response(response=204, description="Administrateur supprime"))
 */
class AdminEndpoints
{
}



