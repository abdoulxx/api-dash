<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Controles",
 *     description="Comparaisons et controles multi-sources."
 * )
 *
 * @OA\Get(path="/api/controle/fdi/{primary}/{secondary}", tags={"Controles"}, summary="Comparer deux FDI", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultat de comparaison"))
 * @OA\Get(path="/api/controle/fcvr/{fcvr}/{declaration}", tags={"Controles"}, summary="Comparer une FCVR et une declaration", security={{"sanctum":{}}}, @OA\Response(response=200, description="Ecarts detectes"))
 * @OA\Get(path="/api/controle/manifeste/{manifeste}/{declaration}", tags={"Controles"}, summary="Comparer manifeste et declaration", security={{"sanctum":{}}}, @OA\Response(response=200, description="Ecarts detectes"))
 * @OA\Get(path="/api/controle/banque/{banqueSad}/{declaration}", tags={"Controles"}, summary="Comparer banque et declaration", security={{"sanctum":{}}}, @OA\Response(response=200, description="Ecarts detectes"))
 * @OA\Post(path="/api/controle/dispatch", tags={"Controles"}, summary="Planifier un controle asynchrone", security={{"sanctum":{}}}, @OA\RequestBody(required=true, @OA\JsonContent(type="object")), @OA\Response(response=202, description="Controle planifie"))
 * @OA\Get(path="/api/controle/result", tags={"Controles"}, summary="Recuperer le dernier resultat en cache", security={{"sanctum":{}}}, @OA\Response(response=200, description="Etat du controle"))
 */
class ControleEndpoints
{
}



