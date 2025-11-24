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

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
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
     * Get all roles with minimal data for dropdowns
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
     * Store a newly created resource in storage.
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
     * Display the specified resource.
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
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
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
     * Get trashed (soft deleted) roles
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
     * Restore a soft deleted role
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
     * Permanently delete a role (force delete)
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
