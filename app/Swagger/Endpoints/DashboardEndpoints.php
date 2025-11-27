<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Indicateurs et metriques metier."
 * )
 *
 * @OA\Get(
 *     path="/api/dashboard",
 *     tags={"Dashboard"},
 *     summary="Vue synthetique",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Indicateurs principaux")
 * )
 *
 * @OA\Get(
 *     path="/api/dashboard/recent-activities",
 *     tags={"Dashboard"},
 *     summary="Activites recentes",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Liste des activites")
 * )
 *
 * @OA\Get(
 *     path="/api/dashboard/user-growth",
 *     tags={"Dashboard"},
 *     summary="Croissance utilisateurs",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Donnees agregees")
 * )
 *
 * @OA\Get(
 *     path="/api/dashboard/action-stats",
 *     tags={"Dashboard"},
 *     summary="Statistiques d'actions",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Donnees agregees")
 * )
 *
 * @OA\Get(
 *     path="/api/dashboard/top-active-users",
 *     tags={"Dashboard"},
 *     summary="Utilisateurs les plus actifs",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Top utilisateurs")
 * )
 *
 * @OA\Get(
 *     path="/api/dashboard/alerts",
 *     tags={"Dashboard"},
 *     summary="Zone d'alerte",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Alertes en cours")
 * )
 *
 * @OA\Get(
 *     path="/api/dashboard/delais",
 *     tags={"Dashboard"},
 *     summary="Suivi des delais",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Indicateurs de delai")
 * )
 */
class DashboardEndpoints
{
}



