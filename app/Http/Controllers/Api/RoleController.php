<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Role;
use App\Support\CacheTagger;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="Gestion complete des roles : CRUD, options, restauration et suppression definitive."
 * )
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/roles",
     *     summary="Lister les roles",
     *     description="Recupere une liste paginee des roles avec possibilite de recherche par nom et description.",
     *     operationId="listRoles",
     *     tags={"Roles"},
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
     *         description="Recherche dans nom et description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des roles",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="10 role(s) disponible(s)"),
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
     *                 @OA\Property(property="total", type="integer", example=10)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'roles:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['roles'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Role::with('permissions');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $roles = $query->latest()->paginate($perPage);

            $message = $roles->total() > 0 
                ? "{$roles->total()} rôle(s) disponible(s)" 
                : 'Aucun rôle trouvé';

            return [
                'status' => 200,
                'message' => $message,
                'data' => RoleResource::collection($roles),
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/options/list",
     *     summary="Lister les roles pour selection",
     *     description="Recupere tous les roles avec des donnees minimales (id, name, description) pour les menus deroulants et selections.",
     *     operationId="getRoleOptions",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des roles pour selection",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="10 role(s) disponible(s) pour selection"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="admin"),
     *                     @OA\Property(property="description", type="string", example="Administrateur du systeme", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getOptions(): JsonResponse
    {
        $roles = Role::select('id', 'name', 'description')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                ];
            });

        $count = $roles->count();
        $message = $count > 0 
            ? "{$count} rôle(s) disponible(s) pour sélection" 
            : 'Aucun rôle disponible';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $roles,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     summary="Creer un nouveau role",
     *     description="Cree un nouveau role avec les informations fournies. Les permissions peuvent etre assignees via permissions (array).",
     *     operationId="createRole",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="editor", description="Nom unique du role"),
     *             @OA\Property(property="description", type="string", example="Editeur de contenu", nullable=true),
     *             @OA\Property(property="guard_name", type="string", example="web", default="web", nullable=true),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"view posts", "edit posts"}, description="Tableau de noms de permissions", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role cree avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="Le role editor a ete cree avec 5 permission(s)"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        // Assign permissions if provided
        if (isset($data['permissions'])) {
            $role->givePermissionTo($data['permissions']);
        }

        $role->load('permissions');

        // Log the creation
        AuditService::logCreate('Role', $role->id, $role->toArray());

        // Invalidate roles cache
        CacheTagger::tags(['roles'])->flush();

        $permissionsCount = $role->permissions->count();
        $permissionsText = $permissionsCount > 0 
            ? " avec {$permissionsCount} permission(s)" 
            : '';

        return response()->json([
            'status' => 201,
            'message' => "Le rôle \"{$role->name}\" a été créé{$permissionsText}",
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     summary="Afficher un role",
     *     description="Recupere les details complets d un role, incluant ses permissions. Supporte deux formats de permissions : flat (par defaut) ou hierarchical.",
     *     operationId="showRole",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du role",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="permissions_format",
     *         in="query",
     *         description="Format des permissions (flat ou hierarchical)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"flat", "hierarchical"}, default="flat")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Details du role",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Role admin charge (15 permission(s))"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role non trouve")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $format = request()->get('permissions_format', 'flat');
        
        $cacheKey = "roles:show:$id:$format";
        $payload = CacheTagger::tags(['roles'])->remember($cacheKey, 300, function () use ($id, $format) {
            $role = Role::with('permissions')->findOrFail($id);
            
            $data = new RoleResource($role);
            $data = $data->toArray(request());
            
            // Add hierarchical permissions if requested
            if ($format === 'hierarchical' && $role->permissions->isNotEmpty()) {
                $hierarchical = [];
                
                foreach ($role->permissions as $permission) {
                    $parts = explode('/', $permission->name);
                    
                    if (count($parts) >= 3) {
                        $module = $parts[0];
                        $page = $parts[1];
                        $action = $parts[2];
                        
                        if (!isset($hierarchical[$module])) {
                            $hierarchical[$module] = ['name' => $module, 'pages' => []];
                        }
                        
                        if (!isset($hierarchical[$module]['pages'][$page])) {
                            $hierarchical[$module]['pages'][$page] = ['name' => $page, 'actions' => []];
                        }
                        
                        $hierarchical[$module]['pages'][$page]['actions'][] = [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $action,
                        ];
                    }
                }
                
                // Convert to indexed array
                $data['permissions_hierarchical'] = array_values(array_map(function ($module) {
                    $module['pages'] = array_values($module['pages']);
                    return $module;
                }, $hierarchical));
            }
            
            $permissionsCount = $role->permissions->count();
            $message = "Rôle \"{$role->name}\" chargé ({$permissionsCount} permission(s))";

            return [
                'status' => 200,
                'message' => $message,
                'data' => $data,
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     summary="Modifier un role",
     *     description="Met a jour les informations d un role. Les permissions peuvent etre mises a jour via permissions (array) qui remplace les permissions existantes.",
     *     operationId="updateRole",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du role",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="editor", nullable=true),
     *             @OA\Property(property="description", type="string", example="Editeur de contenu", nullable=true),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"view posts", "edit posts"}, description="Tableau de noms de permissions pour remplacer les permissions existantes", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role modifie avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Le role editor a ete mis a jour (8 permission(s))"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role non trouve"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $oldValues = $role->toArray();

        $data = $request->validated();

        if (isset($data['name'])) {
            $role->name = $data['name'];
        }
        
        if (isset($data['description'])) {
            $role->description = $data['description'];
        }
        
        $role->save();

        // Update permissions if provided
        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        $role->load('permissions');

        // Log the update
        AuditService::logUpdate('Role', $role->id, $oldValues, $role->toArray());

        // Invalidate roles cache
        CacheTagger::tags(['roles'])->flush();

        $permissionsCount = $role->permissions->count();
        return response()->json([
            'status' => 200,
            'message' => "Le rôle \"{$role->name}\" a été mis à jour ({$permissionsCount} permission(s))",
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     summary="Supprimer un role (soft delete)",
     *     description="Supprime un role de maniere logique (soft delete). Le role peut etre restaure ulterieurement.",
     *     operationId="deleteRole",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du role",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role supprime avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Le role editor a ete supprime (peut etre restaure)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role non trouve")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $oldValues = $role->toArray();

        // Log the deletion before deleting
        AuditService::logDelete('Role', $role->id, $oldValues);

        $role->delete();

        // Invalidate roles cache
        CacheTagger::tags(['roles'])->flush();

        $roleName = $oldValues['name'] ?? 'Rôle';
        return response()->json([
            'status' => 200,
            'message' => "Le rôle \"{$roleName}\" a été supprimé (peut être restauré)",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/trashed/list",
     *     summary="Lister les roles supprimes",
     *     description="Recupere une liste paginee des roles supprimes (soft delete) avec possibilite de recherche.",
     *     operationId="listTrashedRoles",
     *     tags={"Roles"},
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
     *         description="Recherche dans nom et description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des roles supprimes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="3 role(s) supprime(s) trouve(s)"),
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
     *                 @OA\Property(property="total", type="integer", example=3)
     *             )
     *         )
     *     )
     * )
     */
    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'roles:trashed:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['roles'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Role::onlyTrashed()
                ->with('permissions');

            // Search in name, description
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $roles = $query->latest('deleted_at')->paginate($perPage);

            $message = $roles->total() > 0 
                ? "{$roles->total()} rôle(s) supprimé(s) trouvé(s)" 
                : 'Aucun rôle supprimé';

            return [
                'status' => 200,
                'message' => $message,
                'data' => RoleResource::collection($roles),
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{role}/restore",
     *     summary="Restaurer un role supprime",
     *     description="Restaure un role qui a ete supprime (soft delete).",
     *     operationId="restoreRole",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="role",
     *         in="path",
     *         required=true,
     *         description="ID du role a restaurer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role restaure avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Le role editor a ete restaure avec succes (5 permission(s))"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role supprime non trouve")
     * )
     */
    public function restore(int $id): JsonResponse
    {
        $role = Role::onlyTrashed()->findOrFail($id);
        $oldValues = $role->toArray();

        $role->restore();

        $role->load('permissions');

        // Log the restoration
        $roleName = $role->name;
        AuditService::log('restore', "Restored Role {$roleName} #{$role->id}", 'Role', $role->id, $oldValues, $role->toArray());

        // Invalidate roles cache
        CacheTagger::tags(['roles'])->flush();

        $permissionsCount = $role->permissions->count();
        return response()->json([
            'status' => 200,
            'message' => "Le rôle \"{$roleName}\" a été restauré avec succès ({$permissionsCount} permission(s))",
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{role}/force",
     *     summary="Supprimer definitivement un role",
     *     description="Supprime definitivement un role de la base de donnees. Cette action est irreversible. Le role doit etre deja supprime (soft delete) pour pouvoir etre supprime definitivement.",
     *     operationId="forceDeleteRole",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="role",
     *         in="path",
     *         required=true,
     *         description="ID du role a supprimer definitivement",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role supprime definitivement",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Le role editor a ete supprime definitivement")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role supprime non trouve")
     * )
     */
    public function forceDelete(int $id): JsonResponse
    {
        $role = Role::onlyTrashed()->findOrFail($id);
        $oldValues = $role->toArray();

        // Log the permanent deletion before deleting
        $roleName = $role->name;
        AuditService::log('force_delete', "Permanently deleted Role {$roleName} #{$role->id}", 'Role', $role->id, $oldValues, []);

        $role->forceDelete();

        // Invalidate roles cache
        CacheTagger::tags(['roles'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "Le rôle \"{$roleName}\" a été supprimé définitivement",
        ]);
    }
}
