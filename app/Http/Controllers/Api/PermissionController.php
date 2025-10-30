<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignPermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionController extends Controller
{
    /**
     * Display a listing of all permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'permissions:index:' . md5($request->fullUrl());

        $payload = Cache::tags(['permissions'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Permission::query();

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            $permissions = $query->latest()->paginate($perPage);

            return [
                'success' => true,
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
        Cache::tags(['permissions'])->flush();
        Cache::tags(['roles'])->flush();

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to role successfully',
            'data' => [
                'role' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ]);
    }

    /**
     * Assign permissions directly to a user
     */
    public function assignToUser(AssignPermissionRequest $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $data = $request->validated();

        $user->syncPermissions($data['permissions']);

        // Log the permission assignment
        AuditService::logPermissionAssignment('User', $user->id, $data['permissions']);

        // Invalidate permissions/users caches
        Cache::tags(['permissions'])->flush();
        Cache::tags(['users'])->flush();

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to user successfully',
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
        $cacheKey = "permissions:role:$roleId";
        $payload = Cache::tags(['permissions', 'roles'])->remember($cacheKey, 300, function () use ($roleId) {
            $role = Role::with('permissions')->findOrFail($roleId);
            return [
                'success' => true,
                'data' => [
                    'role' => $role->name,
                    'permissions' => PermissionResource::collection($role->permissions),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * Get all permissions for a specific user
     */
    public function getUserPermissions(int $userId): JsonResponse
    {
        $cacheKey = "permissions:user:$userId";
        $payload = Cache::tags(['permissions', 'users'])->remember($cacheKey, 300, function () use ($userId) {
            $user = User::with('permissions', 'roles.permissions')->findOrFail($userId);
            return [
                'success' => true,
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
        Cache::tags(['permissions'])->flush();

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => new PermissionResource($permission),
        ], 201);
    }

    /**
     * Delete a permission
     */
    public function destroy(int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $oldValues = $permission->toArray();

        // Log the deletion
        AuditService::logDelete('Permission', $permission->id, $oldValues);

        $permission->delete();

        // Invalidate permissions cache
        Cache::tags(['permissions'])->flush();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
        ]);
    }
}
