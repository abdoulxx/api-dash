<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Audit Logs",
 *     description="Consultation des journaux d'audit."
 * )
 *
 * @OA\Get(path="/api/audit-logs", tags={"Audit Logs"}, summary="Lister les journaux", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Get(path="/api/audit-logs/{id}", tags={"Audit Logs"}, summary="Afficher un journal", security={{"sanctum":{}}}, @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Journal detaille"))
 * @OA\Get(path="/api/audit-logs/user/{userId}", tags={"Audit Logs"}, summary="Journaux d'un utilisateur", security={{"sanctum":{}}}, @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Historique"))
 * @OA\Get(path="/api/audit-logs/model/{modelType}/{modelId}", tags={"Audit Logs"}, summary="Journaux par modele", security={{"sanctum":{}}}, @OA\Parameter(name="modelType", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="modelId", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Historique filtre"))
 */
class AuditLogEndpoints
{
}


