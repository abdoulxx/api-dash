<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Recherche avancee",
 *     description="Filtrage multi-criteres sur les modules migres."
 * )
 *
 * @OA\Get(path="/api/recherche/manifestes", tags={"Recherche avancee"}, summary="Recherche sur manifestes", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultats filtres"))
 * @OA\Get(path="/api/recherche/fdi", tags={"Recherche avancee"}, summary="Recherche sur FDI", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultats filtres"))
 * @OA\Get(path="/api/recherche/declarations", tags={"Recherche avancee"}, summary="Recherche sur declarations", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultats filtres"))
 * @OA\Get(path="/api/recherche/banque", tags={"Recherche avancee"}, summary="Recherche sur banque", security={{"sanctum":{}}}, @OA\Response(response=200, description="Resultats filtres"))
 */
class RechercheEndpoints
{
}


