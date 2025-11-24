<?php

namespace App\Swagger\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Authentification",
 *     description="Gestion des sessions et des tokens API."
 * )
 *
 * @OA\Post(
 *     path="/api/auth/login",
 *     tags={"Authentification"},
 *     summary="Connexion utilisateur",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email","password"},
 *             @OA\Property(property="email", type="string", format="email"),
 *             @OA\Property(property="password", type="string", format="password")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Authentification reussie"),
 *     @OA\Response(response=401, description="Identifiants invalides")
 * )
 *
 * @OA\Post(
 *     path="/api/auth/logout",
 *     tags={"Authentification"},
 *     summary="Deconnexion",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=204, description="Deconnexion effectuee")
 * )
 *
 * @OA\Get(
 *     path="/api/auth/me",
 *     tags={"Authentification"},
 *     summary="Profil courant",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Utilisateur courant")
 * )
 *
 * @OA\Post(
 *     path="/api/auth/refresh",
 *     tags={"Authentification"},
 *     summary="Rafraichir le token",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Token rafraichi")
 * )
 */
class AuthEndpoints
{
}

