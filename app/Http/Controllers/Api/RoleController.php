<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = Role::with('permissions');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
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
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        // Assign permissions if provided
        if (isset($data['permissions'])) {
            $role->givePermissionTo($data['permissions']);
        }

        $role->load('permissions');

        // Log the creation
        AuditService::logCreate('Role', $role->id, $role->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ]);
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
            $role->save();
        }

        // Update permissions if provided
        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        $role->load('permissions');

        // Log the update
        AuditService::logUpdate('Role', $role->id, $oldValues, $role->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
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

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }
}
