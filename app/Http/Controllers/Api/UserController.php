<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Support\CacheTagger;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Utilisateurs",
 *     description="Gestion complete des utilisateurs applicatifs : CRUD, options, photos, historique d activite, statistiques et restauration."
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Lister les utilisateurs",
     *     description="Recupere une liste paginee des utilisateurs avec possibilite de recherche et filtres par role et statut. Exclut les administrateurs.",
     *     operationId="listUsers",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numero de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d elements par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche dans nom, prenom, nom de famille et email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filtrer par nom de role",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Filtrer par statut (Actif, Inactif, En attente)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Actif", "Inactif", "En attente"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des utilisateurs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="15 utilisateur(s) trouve(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=75)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'users:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $roleFilter = $request->get('role');
            $statutFilter = $request->get('statut');

            $query = User::with(['roles', 'manager'])
                ->where('is_admin', false);

            // Search in name, firstname, lastname, email
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by role
            if ($roleFilter) {
                $query->whereHas('roles', function ($q) use ($roleFilter) {
                    $q->where('name', $roleFilter);
                });
            }

            // Filter by statut
            if ($statutFilter) {
                $query->where('statut', $statutFilter);
            }

            $users = $query->latest()->paginate($perPage);

            $message = $users->total() > 0 
                ? "{$users->total()} utilisateur(s) trouvé(s)" 
                : 'Aucun utilisateur trouvé';

            return [
                'status' => 200,
                'message' => $message,
                'data' => UserResource::collection($users),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Creer un nouvel utilisateur",
     *     description="Cree un nouvel utilisateur avec les informations fournies. Le mot de passe est hashe automatiquement. Les roles peuvent etre assignes via role (string) ou roles (array).",
     *     operationId="createUser",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"firstname", "lastname", "email", "password", "password_confirmation"},
     *             @OA\Property(property="firstname", type="string", example="Jean", description="Prenom"),
     *             @OA\Property(property="lastname", type="string", example="Dupont", description="Nom de famille"),
     *             @OA\Property(property="name", type="string", example="Jean Dupont", description="Nom complet genere automatiquement si non fourni"),
     *             @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", minLength=6),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", minLength=6),
     *             @OA\Property(property="statut", type="string", enum={"Actif", "Inactif", "En attente"}, example="Actif", description="Statut par defaut: Actif"),
     *             @OA\Property(property="fonction", type="string", example="Developpeur", nullable=true),
     *             @OA\Property(property="departement", type="string", example="IT", nullable=true),
     *             @OA\Property(property="manager_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", description="ULID du manager", nullable=true),
     *             @OA\Property(property="phone", type="string", example="+33123456789", nullable=true),
     *             @OA\Property(property="address", type="string", example="123 Rue Example, Paris", nullable=true),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"admin", "user"}, description="Tableau de noms de roles", nullable=true),
     *             @OA\Property(property="role", type="string", example="user", description="Nom d un seul role (alternative a roles)", nullable=true),
     *             @OA\Property(property="is_admin", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Synchronise avec statut si non fourni", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Utilisateur cree avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="L utilisateur Jean Dupont a ete ajoute avec succes"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        
        // Set default name from firstname and lastname if not provided
        if (!isset($data['name']) && isset($data['firstname']) && isset($data['lastname'])) {
            $data['name'] = "{$data['firstname']} {$data['lastname']}";
        }
        
        // Set default statut if not provided
        if (!isset($data['statut'])) {
            $data['statut'] = 'Actif';
        }
        
        // Set is_active based on statut
        $data['is_active'] = $data['statut'] === 'Actif';

        // Handle role assignment - support both 'role' and 'roles'
        $rolesToAssign = [];
        if (isset($data['roles']) && is_array($data['roles'])) {
            $rolesToAssign = $data['roles'];
        } elseif (isset($data['role'])) {
            $rolesToAssign = [$data['role']];
        }
        
        // Remove role fields from data before creating user
        unset($data['roles'], $data['role']);

        $user = User::create($data);

        // Assign roles if provided
        if (!empty($rolesToAssign)) {
            $user->assignRole($rolesToAssign);
        }

        $user->load(['roles', 'manager']);

        // Log the creation
        AuditService::logCreate('User', $user->id, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 201,
            'message' => "L'utilisateur {$userName} a été ajouté avec succès",
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Afficher un utilisateur",
     *     description="Recupere les details complets d un utilisateur avec ses roles, permissions, manager et les 10 derniers logs d audit.",
     *     operationId="showUser",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Details de l utilisateur",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Profil de Jean Dupont charge"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $cacheKey = "users:show:$id";
        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () use ($id) {
            $user = User::with(['roles.permissions', 'manager', 'auditLogs' => function($q) {
                $q->latest()->limit(10);
            }])->findOrFail($id);
            $userName = $user->firstname && $user->lastname 
                ? "{$user->firstname} {$user->lastname}" 
                : $user->email;

            return [
                'status' => 200,
                'message' => "Profil de {$userName} chargé",
                'data' => new UserResource($user),
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Mettre a jour un utilisateur",
     *     description="Met a jour les informations d un utilisateur. Tous les champs sont optionnels. Le mot de passe est hashe s il est fourni. Les roles peuvent etre mis a jour via role ou roles.",
     *     operationId="updateUser",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="firstname", type="string", example="Jean", nullable=true),
     *             @OA\Property(property="lastname", type="string", example="Dupont", nullable=true),
     *             @OA\Property(property="name", type="string", example="Jean Dupont", nullable=true),
     *             @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com", nullable=true),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123", minLength=6, nullable=true),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123", minLength=6, nullable=true),
     *             @OA\Property(property="statut", type="string", enum={"Actif", "Inactif", "En attente"}, example="Actif", nullable=true),
     *             @OA\Property(property="fonction", type="string", example="Developpeur Senior", nullable=true),
     *             @OA\Property(property="departement", type="string", example="IT", nullable=true),
     *             @OA\Property(property="manager_id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV", nullable=true),
     *             @OA\Property(property="phone", type="string", example="+33123456789", nullable=true),
     *             @OA\Property(property="address", type="string", example="123 Rue Example, Paris", nullable=true),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"admin", "user"}, nullable=true),
     *             @OA\Property(property="role", type="string", example="user", nullable=true),
     *             @OA\Property(property="is_admin", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur mis a jour avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Les modifications de Jean Dupont ont ete enregistrees"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        // Update name from firstname and lastname if provided
        if (isset($data['firstname']) || isset($data['lastname'])) {
            $firstname = $data['firstname'] ?? $user->firstname;
            $lastname = $data['lastname'] ?? $user->lastname;
            $data['name'] = "{$firstname} {$lastname}";
        }
        
        // Sync is_active with statut
        if (isset($data['statut'])) {
            $data['is_active'] = $data['statut'] === 'Actif';
        }

        // Handle role assignment - support both 'role' and 'roles'
        $rolesToAssign = [];
        if (isset($data['roles']) && is_array($data['roles'])) {
            $rolesToAssign = $data['roles'];
        } elseif (isset($data['role'])) {
            $rolesToAssign = [$data['role']];
        }
        
        // Remove role fields from data before updating user
        unset($data['roles'], $data['role']);

        $user->update($data);

        // Update roles if provided
        if (!empty($rolesToAssign)) {
            $user->syncRoles($rolesToAssign);
        }

        $user->load(['roles', 'manager']);

        // Log the update
        AuditService::logUpdate('User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "Les modifications de {$userName} ont été enregistrées",
            'data' => new UserResource($user),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Supprimer un utilisateur (soft delete)",
     *     description="Supprime un utilisateur de maniere logicielle (soft delete). L utilisateur peut etre restaure ulterieurement.",
     *     operationId="deleteUser",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur supprime (peut etre restaure)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="L utilisateur Jean Dupont a ete supprime (peut etre restaure)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        // Log the deletion before deleting
        AuditService::logDelete('User', $user->id, $oldValues);

        $user->delete(); // Soft delete

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "L'utilisateur {$userName} a été supprimé (peut être restauré)",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/trashed/list",
     *     summary="Lister les utilisateurs supprimes",
     *     description="Recupere une liste paginee des utilisateurs supprimes (soft deleted) avec possibilite de recherche.",
     *     operationId="listTrashedUsers",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numero de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d elements par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche dans nom, prenom, nom de famille et email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des utilisateurs supprimes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="5 utilisateur(s) supprime(s) trouve(s)"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'users:trashed:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = User::onlyTrashed()
                ->with(['roles', 'manager'])
                ->where('is_admin', false);

            // Search in name, firstname, lastname, email
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->latest('deleted_at')->paginate($perPage);

            $message = $users->total() > 0 
                ? "{$users->total()} utilisateur(s) supprimé(s) trouvé(s)" 
                : 'Aucun utilisateur supprimé';

            return [
                'status' => 200,
                'message' => $message,
                'data' => UserResource::collection($users),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/restore",
     *     summary="Restaurer un utilisateur supprime",
     *     description="Restaure un utilisateur qui a ete supprime (soft delete).",
     *     operationId="restoreUser",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur supprime",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur restaure avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="L utilisateur Jean Dupont a ete restaure avec succes"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur supprime non trouve")
     * )
     */
    public function restore(string $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $oldValues = $user->toArray();

        $user->restore();

        $user->load(['roles', 'manager']);

        // Log the restoration
        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;
        AuditService::log('restore', "Restored User {$userName} #{$user->id}", 'User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "L'utilisateur {$userName} a été restauré avec succès",
            'data' => new UserResource($user),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}/force",
     *     summary="Supprimer definitivement un utilisateur",
     *     description="Supprime definitivement un utilisateur de la base de donnees. Cette action est irreversible. L utilisateur doit etre deja supprime (soft delete).",
     *     operationId="forceDeleteUser",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur a supprimer definitivement",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur supprime definitivement",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="L utilisateur Jean Dupont a ete supprime definitivement")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur supprime non trouve")
     * )
     */
    public function forceDelete(string $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $oldValues = $user->toArray();

        // Log the permanent deletion before deleting
        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;
        AuditService::log('force_delete', "Permanently deleted User {$userName} #{$user->id}", 'User', $user->id, $oldValues, []);

        $user->forceDelete();

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'utilisateur {$userName} a été supprimé définitivement",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}/activity-history",
     *     summary="Historique d activite d un utilisateur",
     *     description="Recupere l historique des activites (logs d audit) d un utilisateur avec possibilite de filtrage par action et dates.",
     *     operationId="getUserActivityHistory",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numero de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d elements par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filtrer par action(s) (separees par virgule: create,update,delete,restore,force_delete)",
     *         required=false,
     *         @OA\Schema(type="string", example="create,update")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Date de debut (format: YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Date de fin (format: YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historique d activite",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="25 activite(s) enregistree(s)"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve")
     * )
     */
    public function activityHistory(string $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);
        
        $perPage = $request->get('per_page', 15);
        $actionFilter = $request->get('action');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = $user->auditLogs()->with('user')->latest();

        // Filter by action (can be multiple actions separated by comma)
        if ($actionFilter) {
            $actions = is_array($actionFilter) ? $actionFilter : explode(',', $actionFilter);
            $query->whereIn('action', $actions);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->paginate($perPage);

        $message = $logs->total() > 0 
            ? "{$logs->total()} activité(s) enregistrée(s)" 
            : 'Aucune activité enregistrée';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/options/departments",
     *     summary="Liste des departements disponibles",
     *     description="Recupere la liste de tous les departements distincts des utilisateurs existants.",
     *     operationId="getDepartments",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des departements",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="5 departement(s) disponible(s)"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"), example={"IT", "RH", "Finance", "Marketing", "Production"})
     *         )
     *     )
     * )
     */
    public function getDepartments(): JsonResponse
    {
        $departments = User::whereNotNull('departement')
            ->distinct()
            ->orderBy('departement')
            ->pluck('departement')
            ->filter()
            ->values();

        $count = $departments->count();
        $message = $count > 0 
            ? "{$count} département(s) disponible(s)" 
            : 'Aucun département disponible';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $departments,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/options/managers",
     *     summary="Liste des managers disponibles",
     *     description="Recupere la liste de tous les managers disponibles (administrateurs ou utilisateurs ayant des subordonnes).",
     *     operationId="getManagers",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des managers",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="10 manager(s) disponible(s)"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getManagers(): JsonResponse
    {
        $cacheKey = 'users:options:managers';
        
        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () {
            $managers = User::where('is_admin', true)
                ->orWhereHas('managedUsers')
                ->select('id', 'name', 'firstname', 'lastname', 'email')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ];
                });

            $count = $managers->count();
            $message = $count > 0 
                ? "{$count} manager(s) disponible(s)" 
                : 'Aucun manager disponible';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $managers,
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/users/options/fonctions",
     *     summary="Liste des fonctions disponibles",
     *     description="Recupere la liste de toutes les fonctions distinctes des utilisateurs existants.",
     *     operationId="getFonctions",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des fonctions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="8 fonction(s) disponible(s)"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"), example={"Developpeur", "Chef de projet", "Analyste", "Designer", "Manager"})
     *         )
     *     )
     * )
     */
    public function getFonctions(): JsonResponse
    {
        $cacheKey = 'users:options:fonctions';
        
        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () {
            $fonctions = User::whereNotNull('fonction')
                ->distinct()
                ->orderBy('fonction')
                ->pluck('fonction')
                ->filter()
                ->values();

            $count = $fonctions->count();
            $message = $count > 0 
                ? "{$count} fonction(s) disponible(s)" 
                : 'Aucune fonction disponible';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $fonctions,
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/users/options/statuts",
     *     summary="Liste des statuts disponibles",
     *     description="Recupere la liste de tous les statuts possibles pour un utilisateur.",
     *     operationId="getStatuts",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des statuts",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="3 statuts disponibles"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"), example={"Actif", "Inactif", "En attente"})
     *         )
     *     )
     * )
     */
    public function getStatuts(): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'message' => '3 statuts disponibles',
            'data' => ['Actif', 'Inactif', 'En attente'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}/statistics",
     *     summary="Statistiques d un utilisateur",
     *     description="Recupere les statistiques detaillees d un utilisateur : jours depuis creation, derniere connexion, nombre d activites, nombre d utilisateurs geres, etc.",
     *     operationId="getUserStatistics",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques de l utilisateur",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Statistiques de Jean Dupont chargees"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve")
     * )
     */
    public function getStatistics(string $id): JsonResponse
    {
        $user = User::withCount(['auditLogs', 'managedUsers'])->findOrFail($id);

        $stats = [
            'days_since_creation' => $user->created_at ? $user->created_at->diffInDays(now()) : 0,
            'hours_since_creation' => $user->created_at ? $user->created_at->diffInHours(now()) % 24 : 0,
            'days_since_last_login' => $user->last_login_at ? $user->last_login_at->diffInDays(now()) : null,
            'hours_since_last_login' => $user->last_login_at ? $user->last_login_at->diffInHours(now()) % 24 : null,
            'total_activities' => $user->audit_logs_count,
            'managed_users_count' => $user->managed_users_count,
            'creation_date' => $user->created_at?->format('d F Y'),
            'last_login_date' => $user->last_login_at?->format('l d F Y'),
        ];

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "Statistiques de {$userName} chargées",
            'data' => $stats,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}/photo",
     *     summary="Recuperer la photo de profil d un utilisateur",
     *     description="Recupere l URL de la photo de profil d un utilisateur si elle existe.",
     *     operationId="getUserPhoto",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Photo de profil recuperee",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aucune photo trouvee",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function getPhoto(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $userName = $user->full_name ?? $user->email;

        if (!$user->photo) {
            return response()->json([
                'status' => 404,
                'message' => "Aucune photo de profil trouvée pour {$userName}",
                'data' => [
                    'has_photo' => false,
                    'photo' => null,
                    'photo_url' => null,
                ],
            ], 404);
        }

        $photoUrl = asset('storage/' . $user->photo);
        $photoExists = Storage::disk('public')->exists($user->photo);

        if (!$photoExists) {
            return response()->json([
                'status' => 404,
                'message' => "Le fichier photo de {$userName} n'existe plus sur le serveur",
                'data' => [
                    'has_photo' => false,
                    'photo' => null,
                    'photo_url' => null,
                ],
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => "Photo de profil de {$userName} récupérée avec succès",
            'data' => [
                'has_photo' => true,
                'photo' => $photoUrl,
                'photo_path' => $user->photo,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/photo",
     *     summary="Uploader ou mettre a jour la photo de profil",
     *     description="Upload une nouvelle photo de profil pour un utilisateur ou remplace l ancienne si elle existe. Formats acceptes: JPEG, PNG, JPG, GIF. Taille maximale: 2MB.",
     *     operationId="uploadUserPhoto",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"photo"},
     *                 @OA\Property(
     *                     property="photo",
     *                     type="string",
     *                     format="binary",
     *                     description="Fichier image (JPEG, PNG, JPG, GIF, max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Photo de profil mise a jour avec succes",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve"),
     *     @OA\Response(response=422, description="Erreur de validation (fichier manquant, format invalide, taille excessive)")
     * )
     */
    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        // Debug: vérifier si le fichier est présent
        if (!$request->hasFile('photo')) {
            return response()->json([
                'status' => 422,
                'message' => 'Le champ photo est requis. Aucun fichier n\'a été reçu.',
                'data' => [
                    'received_files' => $request->allFiles(),
                    'has_file' => $request->hasFile('photo'),
                    'all_input' => array_keys($request->all()),
                ],
            ], 422);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ]);

        // Avec HasUlids, findOrFail fonctionne directement avec l'ULID
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        // Supprimer l'ancienne photo si elle existe
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        // Uploader la nouvelle photo
        $photoPath = $request->file('photo')->store('users/photos', 'public');
        $user->photo = $photoPath;
        $user->save();

        // Log the update
        AuditService::log('update', "La photo de profil de {$user->full_name} a été modifiée", 'User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $photoUrl = asset('storage/' . $photoPath);

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de {$user->full_name} a été mise à jour avec succès",
            'data' => [
                'photo' => $photoUrl,
                'photo_path' => $photoPath,
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}/photo",
     *     summary="Supprimer la photo de profil",
     *     description="Supprime la photo de profil d un utilisateur (fichier et reference en base de donnees).",
     *     operationId="deleteUserPhoto",
     *     tags={"Utilisateurs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Photo de profil supprimee avec succes",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aucune photo trouvee",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function deletePhoto(string $id): JsonResponse
    {
        // Avec HasUlids, findOrFail fonctionne directement avec l'ULID
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        if (!$user->photo) {
            return response()->json([
                'status' => 404,
                'message' => "Aucune photo de profil trouvée pour {$user->full_name}",
            ], 404);
        }

        // Supprimer le fichier
        Storage::disk('public')->delete($user->photo);

        // Supprimer la référence dans la base de données
        $user->photo = null;
        $user->save();

        // Log the update
        AuditService::log('update', "La photo de profil de {$user->full_name} a été supprimée", 'User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de {$user->full_name} a été supprimée avec succès",
        ]);
    }
}
