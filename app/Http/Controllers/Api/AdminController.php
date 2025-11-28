<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
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
 *     name="Administration",
 *     description="Gestion des administrateurs du système."
 * )
 */
class AdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admins",
     *     summary="Lister les administrateurs",
     *     description="Récupère une liste paginée des administrateurs avec possibilité de recherche par nom, prénom, nom de famille et email.",
     *     operationId="listAdmins",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche dans nom, prénom, nom de famille et email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des administrateurs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="5 administrateur(s) trouve(s)"),
             *             @OA\Property(
             *                 property="data",
             *                 type="array",
             *                 @OA\Items(type="object")
             *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=2),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'admins:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['admins'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = User::with('roles')
                ->where('is_admin', true);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $admins = $query->latest()->paginate($perPage);

            $message = $admins->total() > 0
                ? "{$admins->total()} administrateur(s) trouvé(s)" . ($search ? " pour la recherche \"{$search}\"" : "")
                : 'Aucun administrateur trouvé' . ($search ? " pour la recherche \"{$search}\"" : "");

            return [
                'status' => 200,
                'message' => $message,
                'data' => UserResource::collection($admins),
                'meta' => [
                    'current_page' => $admins->currentPage(),
                    'last_page' => $admins->lastPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/admins",
     *     summary="Créer un nouvel administrateur",
     *     description="Crée un nouvel administrateur avec les informations fournies. Le mot de passe est hashé automatiquement. Le flag is_admin est automatiquement défini à true. Les rôles peuvent être assignés via role (string) ou roles (array).",
     *     operationId="createAdmin",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"firstname", "lastname", "email", "password", "password_confirmation"},
     *             @OA\Property(property="firstname", type="string", example="Admin", description="Prénom"),
     *             @OA\Property(property="lastname", type="string", example="Principal", description="Nom de famille"),
     *             @OA\Property(property="name", type="string", example="Admin Principal", description="Nom complet généré automatiquement si non fourni"),
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", minLength=6),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", minLength=6),
     *             @OA\Property(property="statut", type="string", enum={"Actif", "Inactif", "En attente"}, example="Actif", description="Statut par défaut: Actif"),
     *             @OA\Property(property="fonction", type="string", example="Administrateur système", nullable=true),
     *             @OA\Property(property="departement", type="string", example="IT", nullable=true),
     *             @OA\Property(property="phone", type="string", example="+33123456789", nullable=true),
     *             @OA\Property(property="address", type="string", example="123 Rue Example, Paris", nullable=true),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"super-admin", "admin"}, description="Tableau de noms de rôles", nullable=true),
     *             @OA\Property(property="role", type="string", example="super-admin", description="Nom d'un seul rôle (alternative à roles)", nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Synchronisé avec statut si non fourni", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Administrateur créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="L administrateur Admin Principal a ete cree avec succes (2 role(s) assigne(s))"),
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
        $data['is_admin'] = true; // Force admin flag

        // Set default name from firstname and lastname if not provided
        if (!isset($data['name']) && isset($data['firstname']) && isset($data['lastname'])) {
            $data['name'] = trim("{$data['firstname']} {$data['lastname']}");
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

        $admin = User::create($data);

        // Assign roles if provided
        if (!empty($rolesToAssign)) {
            $admin->assignRole($rolesToAssign);
        }

        // Assign roles if provided
        if (isset($data['roles'])) {
            $admin->assignRole($data['roles']);
        }

        $admin->load('roles');

        $adminName = $admin->full_name ?? $admin->email;
        $rolesCount = $admin->roles->count();
        $rolesList = $admin->roles->pluck('name')->implode(', ');

        // Log the creation
        AuditService::log('create', "L'administrateur \"{$adminName}\" a été créé" . ($rolesCount > 0 ? " avec {$rolesCount} rôle(s): {$rolesList}" : ""), 'Admin', $admin->id, null, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'administrateur \"{$adminName}\" a été créé avec succès" . ($rolesCount > 0 ? " ({$rolesCount} rôle(s) assigné(s))" : ""),
            'data' => new UserResource($admin),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admins/{id}",
     *     summary="Afficher un administrateur",
     *     description="Récupère les détails complets d'un administrateur, incluant ses rôles et permissions.",
     *     operationId="showAdmin",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'administrateur",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Administrateur Admin Principal recupere (2 role(s))"),
             *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Administrateur non trouvé")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $cacheKey = "admins:show:$id";
        $payload = CacheTagger::tags(['admins'])->remember($cacheKey, 300, function () use ($id) {
            $admin = User::with('roles.permissions')
                ->where('is_admin', true)
                ->findOrFail($id);
            
            $adminName = $admin->full_name ?? $admin->email;
            $rolesCount = $admin->roles->count();
            
            return [
                'status' => 200,
                'message' => "Administrateur \"{$adminName}\" récupéré" . ($rolesCount > 0 ? " ({$rolesCount} rôle(s))" : ""),
                'data' => new UserResource($admin),
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Put(
     *     path="/api/admins/{id}",
     *     summary="Modifier un administrateur",
     *     description="Met à jour les informations d'un administrateur. Le mot de passe est hashé automatiquement s'il est fourni. Les rôles peuvent être mis à jour via roles (array).",
     *     operationId="updateAdmin",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="firstname", type="string", example="Admin", nullable=true),
     *             @OA\Property(property="lastname", type="string", example="Principal", nullable=true),
     *             @OA\Property(property="name", type="string", example="Admin Principal", nullable=true),
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com", nullable=true),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123", minLength=6, nullable=true),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123", minLength=6, nullable=true),
     *             @OA\Property(property="statut", type="string", enum={"Actif", "Inactif", "En attente"}, example="Actif", nullable=true),
     *             @OA\Property(property="fonction", type="string", example="Administrateur système", nullable=true),
     *             @OA\Property(property="departement", type="string", example="IT", nullable=true),
     *             @OA\Property(property="phone", type="string", example="+33123456789", nullable=true),
     *             @OA\Property(property="address", type="string", example="123 Rue Example, Paris", nullable=true),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"super-admin"}, description="Tableau de noms de rôles pour remplacer les rôles existants", nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Administrateur modifié avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="L administrateur Admin Principal a ete modifie avec succes (3 champ(s) mis a jour)"),
             *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Administrateur non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $admin->update($data);

        // Update roles if provided
        if (isset($data['roles'])) {
            $admin->syncRoles($data['roles']);
        }

        $admin->load('roles');

        $adminName = $admin->full_name ?? $admin->email;
        $rolesCount = $admin->roles->count();
        $updatedFields = array_keys(array_diff_assoc($admin->toArray(), $oldValues));
        $fieldsCount = count($updatedFields);

        // Log the update
        AuditService::log('update', "L'administrateur \"{$adminName}\" a été modifié" . ($fieldsCount > 0 ? " ({$fieldsCount} champ(s) modifié(s))" : ""), 'Admin', $admin->id, $oldValues, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été modifié avec succès" . ($fieldsCount > 0 ? " ({$fieldsCount} champ(s) mis à jour)" : ""),
            'data' => new UserResource($admin),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admins/{id}",
     *     summary="Supprimer un administrateur (soft delete)",
     *     description="Supprime un administrateur de manière logique (soft delete). L'administrateur peut être restauré ultérieurement.",
     *     operationId="deleteAdmin",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Administrateur supprimé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="L administrateur Admin Principal a ete supprime (peut etre restaure)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Administrateur non trouvé")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();
        $adminName = $admin->full_name ?? $admin->email;

        // Log the deletion before deleting
        AuditService::log('delete', "L'administrateur \"{$adminName}\" a été supprimé (soft delete)", 'Admin', $admin->id, $oldValues);

        $admin->delete(); // Soft delete

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été supprimé (peut être restauré)",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admins/trashed/list",
     *     summary="Lister les administrateurs supprimés",
     *     description="Récupère une liste paginée des administrateurs supprimés (soft delete) avec possibilité de recherche.",
     *     operationId="listTrashedAdmins",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche dans nom, prénom, nom de famille et email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des administrateurs supprimés",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="2 administrateur(s) supprime(s) trouve(s)"),
             *             @OA\Property(
             *                 property="data",
             *                 type="array",
             *                 @OA\Items(type="object")
             *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=2)
     *             )
     *         )
     *     )
     * )
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = User::onlyTrashed()
            ->where('is_admin', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->latest('deleted_at')->paginate($perPage);

        $message = $admins->total() > 0
            ? "{$admins->total()} administrateur(s) supprimé(s) trouvé(s)"
            : 'Aucun administrateur supprimé trouvé';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => UserResource::collection($admins),
            'meta' => [
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admins/{id}/restore",
     *     summary="Restaurer un administrateur supprimé",
     *     description="Restaure un administrateur qui a été supprimé (soft delete).",
     *     operationId="restoreAdmin",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur à restaurer",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Administrateur restauré avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="L administrateur Admin Principal a ete restaure avec succes"),
             *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Administrateur supprimé non trouvé")
     * )
     */
    public function restore(string $id): JsonResponse
    {
        $admin = User::onlyTrashed()
            ->where('is_admin', true)
            ->findOrFail($id);
        
        $adminName = $admin->full_name ?? $admin->email;
        $admin->restore();

        AuditService::log('restore', "L'administrateur \"{$adminName}\" a été restauré", 'Admin', $admin->id);
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été restauré avec succès",
            'data' => new UserResource($admin->load('roles')),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admins/{id}/force",
     *     summary="Supprimer définitivement un administrateur",
     *     description="Supprime définitivement un administrateur de la base de données. Cette action est irréversible. L'administrateur doit être déjà supprimé (soft delete) pour pouvoir être supprimé définitivement.",
     *     operationId="forceDeleteAdmin",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur à supprimer définitivement",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Administrateur supprimé définitivement",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="L administrateur Admin Principal a ete supprime definitivement")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Administrateur supprimé non trouvé")
     * )
     */
    public function forceDelete(string $id): JsonResponse
    {
        $admin = User::onlyTrashed()
            ->where('is_admin', true)
            ->findOrFail($id);
        
        $adminName = $admin->full_name ?? $admin->email;
        $oldValues = $admin->toArray();

        $admin->forceDelete();

        AuditService::log('force_delete', "L'administrateur \"{$adminName}\" a été supprimé définitivement", 'Admin', $admin->id, $oldValues);
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été supprimé définitivement",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admins/{id}/photo",
     *     summary="Récupérer la photo de profil d'un administrateur",
     *     description="Récupère l'URL de la photo de profil d'un administrateur si elle existe.",
     *     operationId="getAdminPhoto",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Photo de profil récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Photo de profil de l administrateur Admin Principal recuperee avec succes"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="has_photo", type="boolean", example=true),
     *                 @OA\Property(property="photo", type="string", example="http://localhost:8000/storage/admins/photos/photo.jpg"),
     *                 @OA\Property(property="photo_path", type="string", example="admins/photos/photo.jpg"),
     *                 @OA\Property(
     *                     property="admin",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="01ARZ3NDEKTSV4RRFFQ69G5FAV"),
     *                     @OA\Property(property="name", type="string", example="Admin Principal"),
     *                     @OA\Property(property="email", type="string", example="admin@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Photo non trouvée",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=404),
     *             @OA\Property(property="message", type="string", example="Aucune photo de profil trouvee pour l administrateur Admin Principal"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="has_photo", type="boolean", example=false),
     *                 @OA\Property(property="photo", type="string", nullable=true, example=null),
     *                 @OA\Property(property="photo_url", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function getPhoto(string $id): JsonResponse
    {
        $admin = User::where('is_admin', true)->findOrFail($id);
        $adminName = $admin->full_name ?? $admin->email;

        if (!$admin->photo) {
            return response()->json([
                'status' => 404,
                'message' => "Aucune photo de profil trouvée pour l'administrateur \"{$adminName}\"",
                'data' => [
                    'has_photo' => false,
                    'photo' => null,
                    'photo_url' => null,
                ],
            ], 404);
        }

        $photoUrl = asset('storage/' . $admin->photo);
        $photoExists = Storage::disk('public')->exists($admin->photo);

        if (!$photoExists) {
            return response()->json([
                'status' => 404,
                'message' => "Le fichier photo de l'administrateur \"{$adminName}\" n'existe plus sur le serveur",
                'data' => [
                    'has_photo' => false,
                    'photo' => null,
                    'photo_url' => null,
                ],
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => "Photo de profil de l'administrateur \"{$adminName}\" récupérée avec succès",
            'data' => [
                'has_photo' => true,
                'photo' => $photoUrl,
                'photo_path' => $admin->photo,
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admins/{id}/photo",
     *     summary="Télécharger ou mettre à jour la photo de profil d'un administrateur",
     *     description="Télécharge ou remplace la photo de profil d'un administrateur. Formats acceptés: JPEG, PNG, JPG, GIF. Taille maximale: 2MB. L'ancienne photo est automatiquement supprimée si elle existe.",
     *     operationId="uploadAdminPhoto",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
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
     *         description="Photo de profil mise à jour avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="La photo de profil de l administrateur Admin Principal a ete mise a jour avec succes"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="photo", type="string", example="http://localhost:8000/storage/admins/photos/photo.jpg"),
     *                 @OA\Property(property="photo_path", type="string", example="admins/photos/photo.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Administrateur non trouvé"),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=422),
     *             @OA\Property(property="message", type="string", example="Le champ photo est requis. Aucun fichier n a ete recu.")
     *         )
     *     )
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
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();

        // Supprimer l'ancienne photo si elle existe
        if ($admin->photo) {
            Storage::disk('public')->delete($admin->photo);
        }

        // Uploader la nouvelle photo
        $photoPath = $request->file('photo')->store('admins/photos', 'public');
        $admin->photo = $photoPath;
        $admin->save();

        $adminName = $admin->full_name ?? $admin->email;

        // Log the update
        AuditService::log('update', "La photo de profil de l'administrateur \"{$adminName}\" a été modifiée", 'Admin', $admin->id, $oldValues, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        $photoUrl = asset('storage/' . $photoPath);

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de l'administrateur \"{$adminName}\" a été mise à jour avec succès",
            'data' => [
                'photo' => $photoUrl,
                'photo_path' => $photoPath,
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admins/{id}/photo",
     *     summary="Supprimer la photo de profil d'un administrateur",
     *     description="Supprime la photo de profil d'un administrateur. Le fichier est supprimé du stockage et la référence dans la base de données est effacée.",
     *     operationId="deleteAdminPhoto",
     *     tags={"Administration"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ULID de l'administrateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Photo de profil supprimée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="La photo de profil de l administrateur Admin Principal a ete supprimee avec succes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Photo non trouvée",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=404),
     *             @OA\Property(property="message", type="string", example="Aucune photo de profil trouvee pour l administrateur Admin Principal")
     *         )
     *     )
     * )
     */
    public function deletePhoto(string $id): JsonResponse
    {
        // Avec HasUlids, findOrFail fonctionne directement avec l'ULID
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();
        $adminName = $admin->full_name ?? $admin->email;

        if (!$admin->photo) {
            return response()->json([
                'status' => 404,
                'message' => "Aucune photo de profil trouvée pour l'administrateur \"{$adminName}\"",
            ], 404);
        }

        // Supprimer le fichier
        Storage::disk('public')->delete($admin->photo);

        // Supprimer la référence dans la base de données
        $admin->photo = null;
        $admin->save();

        // Log the update
        AuditService::log('update', "La photo de profil de l'administrateur \"{$adminName}\" a été supprimée", 'Admin', $admin->id, $oldValues, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de l'administrateur \"{$adminName}\" a été supprimée avec succès",
        ]);
    }
}
