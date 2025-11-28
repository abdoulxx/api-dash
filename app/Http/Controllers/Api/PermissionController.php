<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignPermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Support\CacheTagger;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Permissions",
 *     description="Gestion complete des permissions : CRUD, assignation aux roles et utilisateurs, structure hierarchique, restauration et suppression definitive."
 * )
 */
class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/permissions",
     *     summary="Lister les permissions",
     *     description="Recupere une liste paginee des permissions avec possibilite de recherche. Supporte le format flat (par defaut) ou hierarchical via le parametre format.",
     *     operationId="listPermissions",
     *     tags={"Permissions"},
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
     *         description="Recherche dans le nom de la permission",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format de retour (flat ou hierarchical). Si hierarchical, redirige vers /api/permissions/hierarchical",
     *         required=false,
     *         @OA\Schema(type="string", enum={"flat", "hierarchical"}, default="flat")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des permissions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="50 permission(s) disponible(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=4),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $format = $request->get('format', 'flat'); // 'flat' or 'hierarchical'

        if ($format === 'hierarchical') {
            return $this->getHierarchical();
        }

        $cacheKey = 'permissions:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['permissions'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Permission::query();

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            $permissions = $query->latest()->paginate($perPage);

            $message = $permissions->total() > 0 
                ? "{$permissions->total()} permission(s) disponible(s)" 
                : 'Aucune permission trouvée';

            return [
                'status' => 200,
                'message' => $message,
                'data' => PermissionResource::collection($permissions),
                'meta' => [
                    'current_page' => $permissions->currentPage(),
                    'last_page' => $permissions->lastPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/hierarchical",
     *     summary="Lister les permissions en structure hierarchique",
     *     description="Recupere toutes les permissions organisees en structure hierarchique (Module/Page/Action). Les permissions qui ne suivent pas ce format sont placees dans la categorie Other.",
     *     operationId="getHierarchicalPermissions",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Structure hierarchique des permissions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Structure hierarchique chargee : 5 module(s), 50 permission(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Accueil"),
     *                     @OA\Property(
     *                         property="pages",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="name", type="string", example="Page Accueil"),
     *                             @OA\Property(
     *                                 property="actions",
     *                                 type="array",
     *                                 @OA\Items(
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=1),
     *                                     @OA\Property(property="name", type="string", example="Accueil/Page Accueil/Rechercher"),
     *                                     @OA\Property(property="action", type="string", example="Rechercher"),
     *                                     @OA\Property(property="guard_name", type="string", example="web")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getHierarchical(): JsonResponse
    {
        $cacheKey = 'permissions:hierarchical';

        $payload = CacheTagger::tags(['permissions'])->remember($cacheKey, 3600, function () {
            $permissions = Permission::orderBy('name')->get();

            $hierarchical = [];
            
            foreach ($permissions as $permission) {
                // Parse permission name: Module/Page/Action
                $parts = explode('/', $permission->name);
                
                if (count($parts) >= 3) {
                    $module = $parts[0];
                    $page = $parts[1];
                    $action = $parts[2];
                    
                    if (!isset($hierarchical[$module])) {
                        $hierarchical[$module] = [
                            'name' => $module,
                            'pages' => [],
                        ];
                    }
                    
                    if (!isset($hierarchical[$module]['pages'][$page])) {
                        $hierarchical[$module]['pages'][$page] = [
                            'name' => $page,
                            'actions' => [],
                        ];
                    }
                    
                    $hierarchical[$module]['pages'][$page]['actions'][] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'action' => $action,
                        'guard_name' => $permission->guard_name,
                    ];
                } else {
                    // Handle permissions that don't follow the standard format
                    $hierarchical['Other'] = $hierarchical['Other'] ?? ['name' => 'Other', 'pages' => []];
                    $hierarchical['Other']['pages']['General'] = $hierarchical['Other']['pages']['General'] ?? ['name' => 'General', 'actions' => []];
                    $hierarchical['Other']['pages']['General']['actions'][] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'action' => $permission->name,
                        'guard_name' => $permission->guard_name,
                    ];
                }
            }

            // Convert to indexed array
            $result = array_values(array_map(function ($module) {
                $module['pages'] = array_values($module['pages']);
                return $module;
            }, $hierarchical));

            $modulesCount = count($result);
            $totalPermissions = $permissions->count();
            $message = "Structure hiérarchique chargée : {$modulesCount} module(s), {$totalPermissions} permission(s)";

            return [
                'status' => 200,
                'message' => $message,
                'data' => $result,
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/permissions/roles/{roleId}/assign",
     *     summary="Assigner des permissions a un role",
     *     description="Assigne des permissions a un role. Les permissions fournies remplacent toutes les permissions existantes du role (sync).",
     *     operationId="assignPermissionsToRole",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="ID du role",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permissions"},
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"view users", "edit users", "delete users"},
     *                 description="Tableau de noms de permissions"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions assignees avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="5 permission(s) assignee(s) au role admin"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="role", type="string", example="admin"),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role non trouve"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function assignToRole(AssignPermissionRequest $request, int $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);
        $data = $request->validated();

        $role->syncPermissions($data['permissions']);

        // Log the permission assignment
        AuditService::logPermissionAssignment('Role', $role->id, $data['permissions']);

        // Invalidate permissions/roles caches
        CacheTagger::tags(['permissions'])->flush();
        CacheTagger::tags(['roles'])->flush();

        $permissionsCount = count($data['permissions']);
        return response()->json([
            'status' => 200,
            'message' => "{$permissionsCount} permission(s) assignée(s) au rôle \"{$role->name}\"",
            'data' => [
                'role' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/permissions/users/{userId}/assign",
     *     summary="Assigner des permissions a un utilisateur",
     *     description="Assigne des permissions directement a un utilisateur. Les permissions fournies remplacent toutes les permissions directes existantes de l utilisateur (sync).",
     *     operationId="assignPermissionsToUser",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permissions"},
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"view dashboard", "export data"},
     *                 description="Tableau de noms de permissions"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions assignees avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="3 permission(s) assignee(s) a Jean Dupont"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="string", example="Jean Dupont"),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function assignToUser(AssignPermissionRequest $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $data = $request->validated();

        $user->syncPermissions($data['permissions']);

        // Log the permission assignment
        AuditService::logPermissionAssignment('User', $user->id, $data['permissions']);

        // Invalidate permissions/users caches
        CacheTagger::tags(['permissions'])->flush();
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;
        $permissionsCount = count($data['permissions']);

        return response()->json([
            'status' => 200,
            'message' => "{$permissionsCount} permission(s) assignée(s) à {$userName}",
            'data' => [
                'user' => $user->name,
                'permissions' => $user->permissions->pluck('name'),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/roles/{roleId}",
     *     summary="Recuperer les permissions d un role",
     *     description="Recupere toutes les permissions assignees a un role specifique. Supporte deux formats : flat (par defaut) ou hierarchical.",
     *     operationId="getRolePermissions",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="ID du role",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format de retour (flat ou hierarchical)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"flat", "hierarchical"}, default="flat")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions du role",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Le role admin possede 15 permission(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="role", type="string", example="admin"),
     *                 @OA\Property(property="role_id", type="integer", example=1),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="permission_names", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role non trouve")
     * )
     */
    public function getRolePermissions(int $roleId): JsonResponse
    {
        $format = request()->get('format', 'flat'); // 'flat' or 'hierarchical'
        
        $cacheKey = "permissions:role:$roleId:$format";
        $payload = CacheTagger::tags(['permissions', 'roles'])->remember($cacheKey, 300, function () use ($roleId, $format) {
            $role = Role::with('permissions')->findOrFail($roleId);
            
            $permissions = $role->permissions;
            
            if ($format === 'hierarchical') {
                $hierarchical = [];
                
                foreach ($permissions as $permission) {
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
                $result = array_values(array_map(function ($module) {
                    $module['pages'] = array_values($module['pages']);
                    return $module;
                }, $hierarchical));
                
                $permissionsCount = $permissions->count();
                return [
                    'status' => 200,
                    'message' => "Le rôle \"{$role->name}\" possède {$permissionsCount} permission(s)",
                    'data' => [
                        'role' => $role->name,
                        'role_id' => $role->id,
                        'description' => $role->description,
                        'permissions' => $result,
                        'permission_names' => $permissions->pluck('name')->toArray(),
                    ],
                ];
            }
            
            $permissionsCount = $permissions->count();
            return [
                'status' => 200,
                'message' => "Le rôle \"{$role->name}\" possède {$permissionsCount} permission(s)",
                'data' => [
                    'role' => $role->name,
                    'role_id' => $role->id,
                    'description' => $role->description,
                    'permissions' => PermissionResource::collection($permissions),
                    'permission_names' => $permissions->pluck('name')->toArray(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/users/{userId}",
     *     summary="Recuperer les permissions d un utilisateur",
     *     description="Recupere toutes les permissions d un utilisateur, incluant les permissions directes et celles obtenues via les roles.",
     *     operationId="getUserPermissions",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ULID de l utilisateur",
     *         @OA\Schema(type="string", pattern="^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions de l utilisateur",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Jean Dupont : 20 permission(s) (5 directe(s), 15 via role(s))"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="string", example="Jean Dupont"),
     *                 @OA\Property(property="direct_permissions", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="role_permissions", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouve")
     * )
     */
    public function getUserPermissions(string $userId): JsonResponse
    {
        $cacheKey = "permissions:user:$userId";
        $payload = CacheTagger::tags(['permissions', 'users'])->remember($cacheKey, 300, function () use ($userId) {
            $user = User::with('permissions', 'roles.permissions')->findOrFail($userId);
            $userName = $user->firstname && $user->lastname 
                ? "{$user->firstname} {$user->lastname}" 
                : $user->email;
            $totalPermissions = $user->getAllPermissions()->count();
            $directCount = $user->permissions->count();
            $roleCount = $totalPermissions - $directCount;

            return [
                'status' => 200,
                'message' => "{$userName} : {$totalPermissions} permission(s) ({$directCount} directe(s), {$roleCount} via rôle(s))",
                'data' => [
                    'user' => $user->name,
                    'direct_permissions' => PermissionResource::collection($user->permissions),
                    'role_permissions' => PermissionResource::collection($user->getAllPermissions()),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/permissions",
     *     summary="Creer une nouvelle permission",
     *     description="Cree une nouvelle permission avec un nom unique. Le guard_name par defaut est web.",
     *     operationId="createPermission",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="view reports", description="Nom unique de la permission"),
     *             @OA\Property(property="guard_name", type="string", example="web", default="web", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Permission creee avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="La permission view reports a ete ajoutee"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation (nom deja existant)")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'guard_name' => 'sometimes|string',
        ]);

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        // Log the creation
        AuditService::logCreate('Permission', $permission->id, $permission->toArray());

        // Invalidate permissions cache
        CacheTagger::tags(['permissions'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "La permission \"{$permission->name}\" a été ajoutée",
            'data' => new PermissionResource($permission),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/{id}",
     *     summary="Afficher une permission",
     *     description="Recupere les details d une permission specifique.",
     *     operationId="showPermission",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la permission",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Details de la permission",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Permission view reports chargee"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Permission non trouvee")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);

        return response()->json([
            'status' => 200,
            'message' => "Permission \"{$permission->name}\" chargée",
            'data' => new PermissionResource($permission),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/permissions/{id}",
     *     summary="Modifier une permission",
     *     description="Met a jour le nom et/ou le guard_name d une permission existante. Le nom doit rester unique.",
     *     operationId="updatePermission",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la permission",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="view reports updated", description="Nouveau nom unique de la permission"),
     *             @OA\Property(property="guard_name", type="string", example="web", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission modifiee avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="La permission view reports updated a ete mise a jour"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Permission non trouvee"),
     *     @OA\Response(response=422, description="Erreur de validation (nom deja existant)")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $oldValues = $permission->toArray();

        $data = $request->validate([
            'name' => 'required|string|unique:permissions,name,'.$id,
            'guard_name' => 'sometimes|string',
        ]);

        $permission->fill([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? $permission->guard_name,
        ]);
        $permission->save();

        AuditService::logUpdate('Permission', $permission->id, $oldValues, $permission->toArray());

        CacheTagger::tags(['permissions'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La permission \"{$permission->name}\" a été mise à jour",
            'data' => new PermissionResource($permission),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/permissions/{id}",
     *     summary="Supprimer une permission (soft delete)",
     *     description="Supprime une permission de maniere logique (soft delete). La permission peut etre restauree ulterieurement.",
     *     operationId="deletePermission",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la permission",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission supprimee avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="La permission view reports a ete supprimee (peut etre restauree)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Permission non trouvee")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permissionName = $permission->name ?? 'Permission';
        $oldValues = $permission->toArray();

        $permission->delete(); // Soft delete

        AuditService::log('delete', "La permission \"{$permissionName}\" a été supprimée (soft delete)", 'Permission', $permission->id, $oldValues);
        CacheTagger::tags(['permissions'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La permission \"{$permissionName}\" a été supprimée (peut être restaurée)",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/trashed/list",
     *     summary="Lister les permissions supprimees",
     *     description="Recupere une liste paginee des permissions supprimees (soft delete) avec possibilite de recherche.",
     *     operationId="listTrashedPermissions",
     *     tags={"Permissions"},
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
     *         description="Recherche dans le nom de la permission",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des permissions supprimees",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="5 permission(s) supprimee(s) trouvee(s)"),
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
     *                 @OA\Property(property="total", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = Permission::onlyTrashed();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $permissions = $query->latest('deleted_at')->paginate($perPage);

        $message = $permissions->total() > 0
            ? "{$permissions->total()} permission(s) supprimée(s) trouvée(s)"
            : 'Aucune permission supprimée trouvée';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => PermissionResource::collection($permissions),
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/permissions/{id}/restore",
     *     summary="Restaurer une permission supprimee",
     *     description="Restaure une permission qui a ete supprimee (soft delete).",
     *     operationId="restorePermission",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la permission a restaurer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission restauree avec succes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="La permission view reports a ete restauree avec succes"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Permission supprimee non trouvee")
     * )
     */
    public function restore(int $id): JsonResponse
    {
        $permission = Permission::onlyTrashed()->findOrFail($id);
        $permissionName = $permission->name ?? 'Permission';

        $permission->restore();

        AuditService::log('restore', "La permission \"{$permissionName}\" a été restaurée", 'Permission', $permission->id);
        CacheTagger::tags(['permissions'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La permission \"{$permissionName}\" a été restaurée avec succès",
            'data' => new PermissionResource($permission),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/permissions/{id}/force",
     *     summary="Supprimer definitivement une permission",
     *     description="Supprime definitivement une permission de la base de donnees. Cette action est irreversible. La permission doit etre deja supprimee (soft delete) pour pouvoir etre supprimee definitivement.",
     *     operationId="forceDeletePermission",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la permission a supprimer definitivement",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission supprimee definitivement",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="La permission view reports a ete supprimee definitivement")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Permission supprimee non trouvee")
     * )
     */
    public function forceDelete(int $id): JsonResponse
    {
        $permission = Permission::onlyTrashed()->findOrFail($id);
        $permissionName = $permission->name ?? 'Permission';
        $oldValues = $permission->toArray();

        $permission->forceDelete();

        AuditService::log('force_delete', "La permission \"{$permissionName}\" a été supprimée définitivement", 'Permission', $permission->id, $oldValues);
        CacheTagger::tags(['permissions'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La permission \"{$permissionName}\" a été supprimée définitivement",
        ]);
    }
}
