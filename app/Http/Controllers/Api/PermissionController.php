<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignPermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = Permission::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $permissions = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
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
     * Assign permissions to a role
     */
    public function assignToRole(AssignPermissionRequest $request, int $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);
        $data = $request->validated();

        $role->syncPermissions($data['permissions']);

        // Log the permission assignment
        AuditService::logPermissionAssignment('Role', $role->id, $data['permissions']);

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
        $role = Role::with('permissions')->findOrFail($roleId);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role->name,
                'permissions' => PermissionResource::collection($role->permissions),
            ],
        ]);
    }

    /**
     * Get all permissions for a specific user
     */
    public function getUserPermissions(int $userId): JsonResponse
    {
        $user = User::with('permissions', 'roles.permissions')->findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->name,
                'direct_permissions' => PermissionResource::collection($user->permissions),
                'role_permissions' => PermissionResource::collection($user->getAllPermissions()),
            ],
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
        ]);
    }
}
