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
use Illuminate\Support\Facades\Storage;
use App\Support\CacheTagger;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'admins:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['admins'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = User::with('roles')
                ->where('is_admin', true);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $admins = $query->latest()->paginate($perPage);

            $message = $admins->total() > 0
                ? "{$admins->total()} administrateur(s) trouvé(s)" . ($search ? " pour la recherche \"{$search}\"" : "")
                : 'Aucun administrateur trouvé' . ($search ? " pour la recherche \"{$search}\"" : "");

            return [
                'status' => 200,
                'message' => $message,
                'data' => UserResource::collection($admins),
                'meta' => [
                    'current_page' => $admins->currentPage(),
                    'last_page' => $admins->lastPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total(),
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
        $data['is_admin'] = true; // Force admin flag

        // Set default name from firstname and lastname if not provided
        if (!isset($data['name']) && isset($data['firstname']) && isset($data['lastname'])) {
            $data['name'] = trim("{$data['firstname']} {$data['lastname']}");
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

        $admin = User::create($data);

        // Assign roles if provided
        if (!empty($rolesToAssign)) {
            $admin->assignRole($rolesToAssign);
        }

        // Assign roles if provided
        if (isset($data['roles'])) {
            $admin->assignRole($data['roles']);
        }

        $admin->load('roles');

        $adminName = $admin->full_name ?? $admin->email;
        $rolesCount = $admin->roles->count();
        $rolesList = $admin->roles->pluck('name')->implode(', ');

        // Log the creation
        AuditService::log('create', "L'administrateur \"{$adminName}\" a été créé" . ($rolesCount > 0 ? " avec {$rolesCount} rôle(s): {$rolesList}" : ""), 'Admin', $admin->id, null, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'administrateur \"{$adminName}\" a été créé avec succès" . ($rolesCount > 0 ? " ({$rolesCount} rôle(s) assigné(s))" : ""),
            'data' => new UserResource($admin),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $cacheKey = "admins:show:$id";
        $payload = CacheTagger::tags(['admins'])->remember($cacheKey, 300, function () use ($id) {
            $admin = User::with('roles.permissions')
                ->where('is_admin', true)
                ->findOrFail($id);
            
            $adminName = $admin->full_name ?? $admin->email;
            $rolesCount = $admin->roles->count();
            
            return [
                'status' => 200,
                'message' => "Administrateur \"{$adminName}\" récupéré" . ($rolesCount > 0 ? " ({$rolesCount} rôle(s))" : ""),
                'data' => new UserResource($admin),
            ];
        });

        return response()->json($payload);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
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

        $adminName = $admin->full_name ?? $admin->email;
        $rolesCount = $admin->roles->count();
        $updatedFields = array_keys(array_diff_assoc($admin->toArray(), $oldValues));
        $fieldsCount = count($updatedFields);

        // Log the update
        AuditService::log('update', "L'administrateur \"{$adminName}\" a été modifié" . ($fieldsCount > 0 ? " ({$fieldsCount} champ(s) modifié(s))" : ""), 'Admin', $admin->id, $oldValues, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été modifié avec succès" . ($fieldsCount > 0 ? " ({$fieldsCount} champ(s) mis à jour)" : ""),
            'data' => new UserResource($admin),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();
        $adminName = $admin->full_name ?? $admin->email;

        // Log the deletion before deleting
        AuditService::log('delete', "L'administrateur \"{$adminName}\" a été supprimé (soft delete)", 'Admin', $admin->id, $oldValues);

        $admin->delete(); // Soft delete

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été supprimé (peut être restauré)",
        ]);
    }

    /**
     * Liste les administrateurs supprimés (soft delete)
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = User::onlyTrashed()
            ->where('is_admin', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->latest('deleted_at')->paginate($perPage);

        $message = $admins->total() > 0
            ? "{$admins->total()} administrateur(s) supprimé(s) trouvé(s)"
            : 'Aucun administrateur supprimé trouvé';

        return response()->json([
            'status' => 200,
            'message' => $message,
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
     * Restaurer un administrateur supprimé
     */
    public function restore(string $id): JsonResponse
    {
        $admin = User::onlyTrashed()
            ->where('is_admin', true)
            ->findOrFail($id);
        
        $adminName = $admin->full_name ?? $admin->email;
        $admin->restore();

        AuditService::log('restore', "L'administrateur \"{$adminName}\" a été restauré", 'Admin', $admin->id);
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été restauré avec succès",
            'data' => new UserResource($admin->load('roles')),
        ]);
    }

    /**
     * Supprimer définitivement un administrateur
     */
    public function forceDelete(string $id): JsonResponse
    {
        $admin = User::onlyTrashed()
            ->where('is_admin', true)
            ->findOrFail($id);
        
        $adminName = $admin->full_name ?? $admin->email;
        $oldValues = $admin->toArray();

        $admin->forceDelete();

        AuditService::log('force_delete', "L'administrateur \"{$adminName}\" a été supprimé définitivement", 'Admin', $admin->id, $oldValues);
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'administrateur \"{$adminName}\" a été supprimé définitivement",
        ]);
    }

    /**
     * Get admin photo
     */
    public function getPhoto(string $id): JsonResponse
    {
        $admin = User::where('is_admin', true)->findOrFail($id);
        $adminName = $admin->full_name ?? $admin->email;

        if (!$admin->photo) {
            return response()->json([
                'status' => 404,
                'message' => "Aucune photo de profil trouvée pour l'administrateur \"{$adminName}\"",
                'data' => [
                    'has_photo' => false,
                    'photo' => null,
                    'photo_url' => null,
                ],
            ], 404);
        }

        $photoUrl = asset('storage/' . $admin->photo);
        $photoExists = Storage::disk('public')->exists($admin->photo);

        if (!$photoExists) {
            return response()->json([
                'status' => 404,
                'message' => "Le fichier photo de l'administrateur \"{$adminName}\" n'existe plus sur le serveur",
                'data' => [
                    'has_photo' => false,
                    'photo' => null,
                    'photo_url' => null,
                ],
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => "Photo de profil de l'administrateur \"{$adminName}\" récupérée avec succès",
            'data' => [
                'has_photo' => true,
                'photo' => $photoUrl,
                'photo_path' => $admin->photo,
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
            ],
        ]);
    }

    /**
     * Upload or update admin photo
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
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();

        // Supprimer l'ancienne photo si elle existe
        if ($admin->photo) {
            Storage::disk('public')->delete($admin->photo);
        }

        // Uploader la nouvelle photo
        $photoPath = $request->file('photo')->store('admins/photos', 'public');
        $admin->photo = $photoPath;
        $admin->save();

        $adminName = $admin->full_name ?? $admin->email;

        // Log the update
        AuditService::log('update', "La photo de profil de l'administrateur \"{$adminName}\" a été modifiée", 'Admin', $admin->id, $oldValues, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        $photoUrl = asset('storage/' . $photoPath);

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de l'administrateur \"{$adminName}\" a été mise à jour avec succès",
            'data' => [
                'photo' => $photoUrl,
                'photo_path' => $photoPath,
            ],
        ]);
    }

    /**
     * Delete admin photo
     */
    public function deletePhoto(string $id): JsonResponse
    {
        // Avec HasUlids, findOrFail fonctionne directement avec l'ULID
        $admin = User::where('is_admin', true)->findOrFail($id);
        $oldValues = $admin->toArray();
        $adminName = $admin->full_name ?? $admin->email;

        if (!$admin->photo) {
            return response()->json([
                'status' => 404,
                'message' => "Aucune photo de profil trouvée pour l'administrateur \"{$adminName}\"",
            ], 404);
        }

        // Supprimer le fichier
        Storage::disk('public')->delete($admin->photo);

        // Supprimer la référence dans la base de données
        $admin->photo = null;
        $admin->save();

        // Log the update
        AuditService::log('update', "La photo de profil de l'administrateur \"{$adminName}\" a été supprimée", 'Admin', $admin->id, $oldValues, $admin->toArray());

        // Invalidate admins cache
        CacheTagger::tags(['admins'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La photo de profil de l'administrateur \"{$adminName}\" a été supprimée avec succès",
        ]);
    }
}
