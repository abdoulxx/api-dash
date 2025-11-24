<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SydamAuto;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SydamAutoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'sydam-auto:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['sydam-auto'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $annee = $request->get('annee');
            $marque = $request->get('marque');

            $query = SydamAuto::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('NUMERO_BL', 'ilike', "%{$search}%")
                      ->orWhere('NUM_SYDAMAUTO', 'ilike', "%{$search}%")
                      ->orWhere('CHASSIS', 'ilike', "%{$search}%")
                      ->orWhere('IMMATRICULATION', 'ilike', "%{$search}%")
                      ->orWhere('IMPORTATEUR', 'ilike', "%{$search}%")
                      ->orWhere('MARQUE', 'ilike', "%{$search}%")
                      ->orWhere('MODELE', 'ilike', "%{$search}%");
                });
            }

            if ($annee) {
                $query->where('ANNEE', $annee);
            }

            if ($marque) {
                $query->where('MARQUE', 'ilike', "%{$marque}%");
            }

            $vehicules = $query->latest('DATE_CIVIO')->paginate($perPage);

            $message = $vehicules->total() > 0
                ? "{$vehicules->total()} véhicule(s) SYDAM Auto trouvé(s)" . ($search ? " pour la recherche \"{$search}\"" : "")
                : 'Aucun véhicule SYDAM Auto trouvé' . ($search ? " pour la recherche \"{$search}\"" : "");

            return [
                'status' => 200,
                'message' => $message,
                'data' => $vehicules->items(),
                'meta' => [
                    'current_page' => $vehicules->currentPage(),
                    'last_page' => $vehicules->lastPage(),
                    'per_page' => $vehicules->perPage(),
                    'total' => $vehicules->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function show(string $id): JsonResponse
    {
        $cacheKey = "sydam-auto:show:{$id}";
        
        $payload = CacheTagger::tags(['sydam-auto'])->remember($cacheKey, 300, function () use ($id) {
            $vehicule = SydamAuto::findOrFail($id);

            return [
                'status' => 200,
                'message' => "Véhicule SYDAM Auto #{$id} chargé",
                'data' => $vehicule,
            ];
        });

        return response()->json($payload);
    }
}

