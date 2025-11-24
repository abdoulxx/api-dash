<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\Banque;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BanqueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'banque:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $anneeDvt = $request->get('annee_dvt');

            $query = Banque::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('NUM_DVT', 'ilike', "%{$search}%")
                      ->orWhere('REF_DDU', 'ilike', "%{$search}%")
                      ->orWhere('CDA_AC', 'ilike', "%{$search}%");
                });
            }

            if ($anneeDvt) {
                $query->where('ANNEE_DVT', $anneeDvt);
            }

            $banques = $query->latest('DATE_DVT')->paginate($perPage);

            $message = $banques->total() > 0
                ? "{$banques->total()} enregistrement(s) Banque trouvé(s)" . ($search ? " pour la recherche \"{$search}\"" : "")
                : 'Aucun enregistrement Banque trouvé' . ($search ? " pour la recherche \"{$search}\"" : "");

            return [
                'status' => 200,
                'message' => $message,
                'data' => $banques->items(),
                'meta' => [
                    'current_page' => $banques->currentPage(),
                    'last_page' => $banques->lastPage(),
                    'per_page' => $banques->perPage(),
                    'total' => $banques->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function show(string $id): JsonResponse
    {
        $cacheKey = "banque:show:{$id}";
        
        $payload = CacheTagger::tags(['banque'])->remember($cacheKey, 300, function () use ($id) {
            $banque = Banque::where('ID', $id)->firstOrFail();

            $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
            return [
                'status' => 200,
                'message' => "L'enregistrement Banque \"{$numero}\" a été chargé avec succès",
                'data' => $banque,
            ];
        });

        return response()->json($payload);
    }

    public function store(StoreBanqueRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $banque = Banque::create($validated);

        // Log the creation
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        AuditService::log('create', "L'enregistrement Banque \"{$numero}\" a été créé", 'Banque', $banque->ID, null, $banque->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'enregistrement Banque \"{$numero}\" a été créé avec succès",
            'data' => $banque->fresh()->toArray(),
        ], 201);
    }

    public function update(UpdateBanqueRequest $request, string $id): JsonResponse
    {
        $banque = Banque::where('ID', $id)->firstOrFail();
        $oldValues = $banque->toArray();
        
        $validated = $request->validated();
        $banque->fill($validated);
        $banque->save();

        // Log the update
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        AuditService::log('update', "L'enregistrement Banque \"{$numero}\" a été modifié", 'Banque', $banque->ID, $oldValues, $banque->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été modifié avec succès",
            'data' => $banque->fresh()->toArray(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $banque = Banque::where('ID', $id)->firstOrFail();
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        $oldValues = $banque->toArray();
        
        $banque->delete();

        // Log the deletion
        AuditService::log('delete', "L'enregistrement Banque \"{$numero}\" a été supprimé", 'Banque', $banque->ID, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été supprimé (peut être restauré)",
            'data' => null,
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'banque:trashed:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Banque::onlyTrashed();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('NUM_DVT', 'ilike', "%{$search}%")
                      ->orWhere('REF_DDU', 'ilike', "%{$search}%")
                      ->orWhere('CDA_AC', 'ilike', "%{$search}%");
                });
            }

            $banques = $query->latest('deleted_at')->paginate($perPage);

            $message = $banques->total() > 0 
                ? "{$banques->total()} enregistrement(s) Banque supprimé(s) trouvé(s)" 
                : 'Aucun enregistrement Banque supprimé';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $banques->items(),
                'meta' => [
                    'current_page' => $banques->currentPage(),
                    'last_page' => $banques->lastPage(),
                    'per_page' => $banques->perPage(),
                    'total' => $banques->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function restore(string $id): JsonResponse
    {
        $banque = Banque::onlyTrashed()->where('ID', $id)->firstOrFail();
        $oldValues = $banque->toArray();

        $banque->restore();

        // Log the restoration
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        AuditService::log('restore', "L'enregistrement Banque \"{$numero}\" a été restauré", 'Banque', $banque->ID, $oldValues, $banque->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été restauré avec succès",
            'data' => $banque->fresh()->toArray(),
        ]);
    }

    public function forceDelete(string $id): JsonResponse
    {
        $banque = Banque::onlyTrashed()->where('ID', $id)->firstOrFail();
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        $oldValues = $banque->toArray();

        // Log the permanent deletion before deleting
        AuditService::log('force_delete', "L'enregistrement Banque \"{$numero}\" a été supprimé définitivement", 'Banque', $banque->ID, $oldValues, []);

        $banque->forceDelete();

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été supprimé définitivement",
            'data' => null,
        ]);
    }
}

