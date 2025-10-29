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

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = User::with('roles')
            ->where('is_admin', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
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
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['is_admin'] = true; // Force admin flag

        $admin = User::create($data);

        // Assign roles if provided
        if (isset($data['roles'])) {
            $admin->assignRole($data['roles']);
        }

        $admin->load('roles');

        // Log the creation
        AuditService::logCreate('Admin', $admin->id, $admin->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Admin created successfully',
            'data' => new UserResource($admin),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $admin = User::with('roles.permissions')
            ->where('is_admin', true)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new UserResource($admin),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
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

        // Log the update
        AuditService::logUpdate('Admin', $admin->id, $oldValues, $admin->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Admin updated successfully',
            'data' => new UserResource($admin),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();

        // Log the deletion before deleting
        AuditService::logDelete('Admin', $admin->id, $oldValues);

        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin deleted successfully',
        ]);
    }
}
