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

class PermissionController extends Controller
{
    /**
     * Display a listing of all permissions.
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
     * Get permissions in hierarchical structure (module/page/action)
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
     * Assign permissions to a role
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
     * Assign permissions directly to a user
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
     * Get all permissions for a specific role
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
     * Get all permissions for a specific user
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
     * Create a new permission
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
     * Display the specified permission.
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
     * Update an existing permission.
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
     * Delete a permission (soft delete)
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
     * List trashed permissions
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
     * Restore a trashed permission
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
     * Permanently delete a permission
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
