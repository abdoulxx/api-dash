<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Support\CacheTagger;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'users:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $roleFilter = $request->get('role');
            $statutFilter = $request->get('statut');

            $query = User::with(['roles', 'manager'])
                ->where('is_admin', false);

            // Search in name, firstname, lastname, email
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by role
            if ($roleFilter) {
                $query->whereHas('roles', function ($q) use ($roleFilter) {
                    $q->where('name', $roleFilter);
                });
            }

            // Filter by statut
            if ($statutFilter) {
                $query->where('statut', $statutFilter);
            }

            $users = $query->latest()->paginate($perPage);

            $message = $users->total() > 0 
                ? "{$users->total()} utilisateur(s) trouvé(s)" 
                : 'Aucun utilisateur trouvé';

            return [
                'status' => 200,
                'message' => $message,
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
        
        // Set default name from firstname and lastname if not provided
        if (!isset($data['name']) && isset($data['firstname']) && isset($data['lastname'])) {
            $data['name'] = "{$data['firstname']} {$data['lastname']}";
        }
        
        // Set default statut if not provided
        if (!isset($data['statut'])) {
            $data['statut'] = 'Actif';
        }
        
        // Set is_active based on statut
        $data['is_active'] = $data['statut'] === 'Actif';

        // Handle role assignment - support both 'role' and 'roles'
        $rolesToAssign = [];
        if (isset($data['roles']) && is_array($data['roles'])) {
            $rolesToAssign = $data['roles'];
        } elseif (isset($data['role'])) {
            $rolesToAssign = [$data['role']];
        }
        
        // Remove role fields from data before creating user
        unset($data['roles'], $data['role']);

        $user = User::create($data);

        // Assign roles if provided
        if (!empty($rolesToAssign)) {
            $user->assignRole($rolesToAssign);
        }

        $user->load(['roles', 'manager']);

        // Log the creation
        AuditService::logCreate('User', $user->id, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 201,
            'message' => "L'utilisateur {$userName} a été ajouté avec succès",
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $cacheKey = "users:show:$id";
        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () use ($id) {
            $user = User::with(['roles.permissions', 'manager', 'auditLogs' => function($q) {
                $q->latest()->limit(10);
            }])->findOrFail($id);
            $userName = $user->firstname && $user->lastname 
                ? "{$user->firstname} {$user->lastname}" 
                : $user->email;

            return [
                'status' => 200,
                'message' => "Profil de {$userName} chargé",
                'data' => new UserResource($user),
            ];
        });

        return response()->json($payload);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        // Update name from firstname and lastname if provided
        if (isset($data['firstname']) || isset($data['lastname'])) {
            $firstname = $data['firstname'] ?? $user->firstname;
            $lastname = $data['lastname'] ?? $user->lastname;
            $data['name'] = "{$firstname} {$lastname}";
        }
        
        // Sync is_active with statut
        if (isset($data['statut'])) {
            $data['is_active'] = $data['statut'] === 'Actif';
        }

        // Handle role assignment - support both 'role' and 'roles'
        $rolesToAssign = [];
        if (isset($data['roles']) && is_array($data['roles'])) {
            $rolesToAssign = $data['roles'];
        } elseif (isset($data['role'])) {
            $rolesToAssign = [$data['role']];
        }
        
        // Remove role fields from data before updating user
        unset($data['roles'], $data['role']);

        $user->update($data);

        // Update roles if provided
        if (!empty($rolesToAssign)) {
            $user->syncRoles($rolesToAssign);
        }

        $user->load(['roles', 'manager']);

        // Log the update
        AuditService::logUpdate('User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "Les modifications de {$userName} ont été enregistrées",
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        // Log the deletion before deleting
        AuditService::logDelete('User', $user->id, $oldValues);

        $user->delete(); // Soft delete

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "L'utilisateur {$userName} a été supprimé (peut être restauré)",
        ]);
    }

    /**
     * Get trashed (soft deleted) users
     */
    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'users:trashed:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = User::onlyTrashed()
                ->with(['roles', 'manager'])
                ->where('is_admin', false);

            // Search in name, firstname, lastname, email
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->latest('deleted_at')->paginate($perPage);

            $message = $users->total() > 0 
                ? "{$users->total()} utilisateur(s) supprimé(s) trouvé(s)" 
                : 'Aucun utilisateur supprimé';

            return [
                'status' => 200,
                'message' => $message,
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
     * Restore a soft deleted user
     */
    public function restore(string $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $oldValues = $user->toArray();

        $user->restore();

        $user->load(['roles', 'manager']);

        // Log the restoration
        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;
        AuditService::log('restore', "Restored User {$userName} #{$user->id}", 'User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "L'utilisateur {$userName} a été restauré avec succès",
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Permanently delete a user (force delete)
     */
    public function forceDelete(string $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $oldValues = $user->toArray();

        // Log the permanent deletion before deleting
        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;
        AuditService::log('force_delete', "Permanently deleted User {$userName} #{$user->id}", 'User', $user->id, $oldValues, []);

        $user->forceDelete();

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'utilisateur {$userName} a été supprimé définitivement",
        ]);
    }

    /**
     * Get user activity history
     */
    public function activityHistory(string $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);
        
        $perPage = $request->get('per_page', 15);
        $actionFilter = $request->get('action');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = $user->auditLogs()->with('user')->latest();

        // Filter by action (can be multiple actions separated by comma)
        if ($actionFilter) {
            $actions = is_array($actionFilter) ? $actionFilter : explode(',', $actionFilter);
            $query->whereIn('action', $actions);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->paginate($perPage);

        $message = $logs->total() > 0 
            ? "{$logs->total()} activité(s) enregistrée(s)" 
            : 'Aucune activité enregistrée';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get available departments
     */
    public function getDepartments(): JsonResponse
    {
        $departments = User::whereNotNull('departement')
            ->distinct()
            ->orderBy('departement')
            ->pluck('departement')
            ->filter()
            ->values();

        $count = $departments->count();
        $message = $count > 0 
            ? "{$count} département(s) disponible(s)" 
            : 'Aucun département disponible';

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $departments,
        ]);
    }

    /**
     * Get available managers
     */
    public function getManagers(): JsonResponse
    {
        $cacheKey = 'users:options:managers';
        
        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () {
            $managers = User::where('is_admin', true)
                ->orWhereHas('managedUsers')
                ->select('id', 'name', 'firstname', 'lastname', 'email')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ];
                });

            $count = $managers->count();
            $message = $count > 0 
                ? "{$count} manager(s) disponible(s)" 
                : 'Aucun manager disponible';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $managers,
            ];
        });

        return response()->json($payload);
    }

    /**
     * Get available fonctions
     */
    public function getFonctions(): JsonResponse
    {
        $cacheKey = 'users:options:fonctions';
        
        $payload = CacheTagger::tags(['users'])->remember($cacheKey, 300, function () {
            $fonctions = User::whereNotNull('fonction')
                ->distinct()
                ->orderBy('fonction')
                ->pluck('fonction')
                ->filter()
                ->values();

            $count = $fonctions->count();
            $message = $count > 0 
                ? "{$count} fonction(s) disponible(s)" 
                : 'Aucune fonction disponible';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $fonctions,
            ];
        });

        return response()->json($payload);
    }

    /**
     * Get available statuts
     */
    public function getStatuts(): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'message' => '3 statuts disponibles',
            'data' => ['Actif', 'Inactif', 'En attente'],
        ]);
    }

    /**
     * Get user statistics
     */
    public function getStatistics(string $id): JsonResponse
    {
        $user = User::withCount(['auditLogs', 'managedUsers'])->findOrFail($id);

        $stats = [
            'days_since_creation' => $user->created_at ? $user->created_at->diffInDays(now()) : 0,
            'hours_since_creation' => $user->created_at ? $user->created_at->diffInHours(now()) % 24 : 0,
            'days_since_last_login' => $user->last_login_at ? $user->last_login_at->diffInDays(now()) : null,
            'hours_since_last_login' => $user->last_login_at ? $user->last_login_at->diffInHours(now()) % 24 : null,
            'total_activities' => $user->audit_logs_count,
            'managed_users_count' => $user->managed_users_count,
            'creation_date' => $user->created_at?->format('d F Y'),
            'last_login_date' => $user->last_login_at?->format('l d F Y'),
        ];

        $userName = $user->firstname && $user->lastname 
            ? "{$user->firstname} {$user->lastname}" 
            : $user->email;

        return response()->json([
            'status' => 200,
            'message' => "Statistiques de {$userName} chargées",
            'data' => $stats,
        ]);
    }

    /**
     * Upload or update user photo
     */
    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        // Debug: vérifier si le fichier est présent
        if (!$request->hasFile('photo')) {
            return response()->json([
                'status' => 422,
                'message' => 'Le champ photo est requis. Aucun fichier n\'a été reçu.',
                'data' => [
                    'received_files' => $request->allFiles(),
                    'has_file' => $request->hasFile('photo'),
                    'all_input' => array_keys($request->all()),
                ],
            ], 422);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ]);

        // Avec HasUlids, findOrFail fonctionne directement avec l'ULID
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        // Supprimer l'ancienne photo si elle existe
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        // Uploader la nouvelle photo
        $photoPath = $request->file('photo')->store('users/photos', 'public');
        $user->photo = $photoPath;
        $user->save();

        // Log the update
        AuditService::log('update', "La photo de profil de {$user->full_name} a été modifiée", 'User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        $photoUrl = asset('storage/' . $photoPath);

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de {$user->full_name} a été mise à jour avec succès",
            'data' => [
                'photo' => $photoUrl,
                'photo_path' => $photoPath,
            ],
        ]);
    }

    /**
     * Delete user photo
     */
    public function deletePhoto(string $id): JsonResponse
    {
        // Avec HasUlids, findOrFail fonctionne directement avec l'ULID
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();

        if (!$user->photo) {
            return response()->json([
                'status' => 404,
                'message' => "Aucune photo de profil trouvée pour {$user->full_name}",
            ], 404);
        }

        // Supprimer le fichier
        Storage::disk('public')->delete($user->photo);

        // Supprimer la référence dans la base de données
        $user->photo = null;
        $user->save();

        // Log the update
        AuditService::log('update', "La photo de profil de {$user->full_name} a été supprimée", 'User', $user->id, $oldValues, $user->toArray());

        // Invalidate users cache
        CacheTagger::tags(['users'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de {$user->full_name} a été supprimée avec succès",
        ]);
    }
}
