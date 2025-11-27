<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Banque TVF",
 *     description="Gestion des transferts de valeurs financieres."
 * )
 *
 * @OA\Get(path="/api/banque/tvf", tags={"Banque TVF"}, summary="Lister les TVF", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste paginee"))
 * @OA\Post(path="/api/banque/tvf", tags={"Banque TVF"}, summary="Creer un TVF", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=201, description="Enregistrement cree"))
 * @OA\Get(path="/api/banque/tvf/{id}", tags={"Banque TVF"}, summary="Afficher un TVF", security={{"sanctum":{}}}, @OA\Response(response=200, description="Enregistrement"))
 * @OA\Put(path="/api/banque/tvf/{id}", tags={"Banque TVF"}, summary="Mettre a jour un TVF", security={{"sanctum":{}}}, @OA\Response(response=200, description="Mis a jour"))
 * @OA\Delete(path="/api/banque/tvf/{id}", tags={"Banque TVF"}, summary="Supprimer un TVF", security={{"sanctum":{}}}, @OA\Response(response=204, description="Supprime"))
 * @OA\Get(path="/api/banque/tvf/{id}/fdi", tags={"Banque TVF"}, summary="FDI associee", security={{"sanctum":{}}}, @OA\Response(response=200, description="Details FDI"))
 * @OA\Get(path="/api/banque/tvf/{id}/comparaisons", tags={"Banque TVF"}, summary="Comparaisons associees", security={{"sanctum":{}}}, @OA\Response(response=200, description="Liste des comparaisons"))
 * @OA\Post(path="/api/banque/tvf/{id}/validate", tags={"Banque TVF"}, summary="Valider un TVF", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultat de validation"))
 */
class BanqueTvfEndpoints
{
}



