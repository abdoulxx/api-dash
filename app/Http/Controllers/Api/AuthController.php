<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            AuditService::logLogin($credentials['email'], false);

            return response()->json([
                'status' => 401,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => 403,
                'message' => 'Account is inactive',
            ], 403);
        }

        // Ensure user has only one active session at a time
        $user->tokens()->delete();

        // Generate tokens with length between 180 and 220 characters
        $plainAccessToken = Str::random(rand(180, 220));
        $plainRefreshToken = Str::random(rand(180, 220));

        $user->tokens()->create([
            'name' => 'access_token',
            'token' => hash('sha256', $plainAccessToken),
            'abilities' => ['access'],
        ]);

        $user->tokens()->create([
            'name' => 'refresh_token',
            'token' => hash('sha256', $plainRefreshToken),
            'abilities' => ['refresh'],
        ]);

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        // Log successful login
        AuditService::logLogin($user->email, true);

        $user->load('roles.permissions');

        return response()->json([
            'status' => 200,
            'refresh' => $plainRefreshToken,
            'access' => $plainAccessToken,
            'user' => [
                'id' => $user->id,
                'password' => $user->getAuthPassword(),
                'is_superuser' => $user->hasRole('super-admin'),
                'username' => $user->name,
                'email' => $user->email,
                'is_staff' => $user->is_admin,
                'is_active' => $user->is_active,
                'phone' => $user->phone,
                'address' => $user->address,
                'created_at' => optional($user->created_at)->toIso8601String(),
                'updated_at' => optional($user->updated_at)->toIso8601String(),
                'is_deleted' => (bool) $user->deleted_at,
                'is_updated' => (bool) $user->updated_at,
                'deleted_at' => $user->deleted_at,
                'groups' => $user->roles->pluck('name'),
                'user_permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'message' => 'Connexion réussie !',
        ]);
    }

    /**
     * Logout user (Revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        // Log logout before revoking token
        AuditService::logLogout();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return response()->json([
            'status' => 200,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Refresh token (optional)
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current tokens
        $user->tokens()->delete();

        // Generate new tokens with length between 180 and 220 characters
        $plainAccessToken = Str::random(rand(180, 220));
        $plainRefreshToken = Str::random(rand(180, 220));

        $user->tokens()->create([
            'name' => 'access_token',
            'token' => hash('sha256', $plainAccessToken),
            'abilities' => ['access'],
        ]);

        $user->tokens()->create([
            'name' => 'refresh_token',
            'token' => hash('sha256', $plainRefreshToken),
            'abilities' => ['refresh'],
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Token refreshed',
            'data' => [
                'access' => $plainAccessToken,
                'refresh' => $plainRefreshToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
