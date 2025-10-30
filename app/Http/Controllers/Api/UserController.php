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

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'users:index:' . md5($request->fullUrl());

        $payload = Cache::tags(['users'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = User::with('roles')
                ->where('is_admin', false);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->latest()->paginate($perPage);

            return [
                'success' => true,
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
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        // Assign roles if provided
        if (isset($data['roles'])) {
            $user->assignRole($data['roles']);
        }

        $user->load('roles');

        // Log the creation
        AuditService::logCreate('User', $user->id, $user->toArray());

        // Invalidate users cache
        Cache::tags(['users'])->flush();

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $cacheKey = "users:show:$id";
        $payload = Cache::tags(['users'])->remember($cacheKey, 300, function () use ($id) {
            $user = User::with('roles.permissions')->findOrFail($id);
            return [
                'success' => true,
                'data' => new UserResource($user),
            ];
        });

        return response()->json($payload);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        // Update roles if provided
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $user->load('roles');

        // Log the update
        AuditService::logUpdate('User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        Cache::tags(['users'])->flush();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        // Log the deletion before deleting
        AuditService::logDelete('User', $user->id, $oldValues);

        $user->delete();

        // Invalidate users cache
        Cache::tags(['users'])->flush();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }
}
