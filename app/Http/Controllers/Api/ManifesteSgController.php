<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeclarationSg;
use App\Models\ManifesteSg;
use App\Models\ManifesteTc;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Manifestes SG",
 *     description="Gestion des manifestes du segment SG."
 * )
 */
class ManifesteSgController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/manifestes/sg",
     *     summary="Liste des manifestes SG",
     *     operationId="listManifestesSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="code_bureau",
     *         in="query",
     *         description="Filtrer par code bureau",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="num_manifeste",
     *         in="query",
     *         description="Filtrer par numero de manifeste (recherche partielle)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'elements par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des manifestes",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'manifestes:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['manifestes', 'manifeste_sg'])->remember($cacheKey, 300, function () use ($request) {
            $query = ManifesteSg::query();

            if ($request->filled('code_bureau')) {
                $query->where('code_bureau', $request->get('code_bureau'));
            }

            if ($request->filled('num_manifeste')) {
                $query->where('num_manifeste', 'like', '%'.$request->get('num_manifeste').'%');
            }

            $manifestes = $query->paginate($request->integer('per_page', 15));

            $message = $manifestes->total() > 0
                ? "{$manifestes->total()} manifeste(s) récupéré(s) avec succès"
                : "Aucun manifeste trouvé";

            return [
                'status' => 200,
                'message' => $message,
                'data' => $manifestes->items(),
                'meta' => [
                    'current_page' => $manifestes->currentPage(),
                    'last_page' => $manifestes->lastPage(),
                    'per_page' => $manifestes->perPage(),
                    'total' => $manifestes->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/manifestes/sg",
     *     summary="Creer un manifeste SG",
     *     operationId="storeManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"instance_id","num_manifeste"},
     *             @OA\Property(property="instance_id", type="integer"),
     *             @OA\Property(property="num_manifeste", type="string"),
     *             @OA\Property(property="code_bureau", type="string"),
     *             @OA\Property(property="date_arrivee", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Manifeste cree",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instance_id' => 'required|integer|unique:manifeste_sg,instance_id',
            'num_manifeste' => 'required|string|max:255',
        ]);

        $manifeste = ManifesteSg::create($validated + $request->except(['instance_id', 'num_manifeste']));

        // Log the creation
        $numero = $manifeste->num_manifeste ?? "N°{$manifeste->instance_id}";
        AuditService::log('create', "Le manifeste \"{$numero}\" a été créé", 'ManifesteSg', $manifeste->instance_id, null, $manifeste->toArray());

        // Invalider le cache
        CacheTagger::tags(['manifestes', 'manifeste_sg'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "Le manifeste \"{$numero}\" a été créé avec succès",
            'data' => $manifeste->fresh()->toArray(),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}",
     *     summary="Afficher un manifeste SG",
     *     operationId="showManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         description="Identifiant (id ou instance_id) du manifeste",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Manifeste trouve",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Manifeste introuvable")
     * )
     */
    public function show(string $manifesteId): JsonResponse
    {
        $cacheKey = "manifestes:show:{$manifesteId}";

        $payload = CacheTagger::tags(['manifestes', 'manifeste_sg'])->remember($cacheKey, 300, function () use ($manifesteId) {
            $manifeste = $this->findManifeste($manifesteId);
            $numero = $manifeste->num_manifeste ?? "N°{$manifeste->instance_id}";
            
            return [
                'status' => 200,
                'message' => "Détails du manifeste \"{$numero}\" récupérés avec succès",
                'data' => $manifeste->toArray(),
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Put(
     *     path="/api/manifestes/sg/{manifeste}",
     *     summary="Mettre a jour un manifeste SG",
     *     operationId="updateManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Manifeste mis a jour",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Manifeste introuvable")
     * )
     */
    public function update(Request $request, string $manifesteId): JsonResponse
    {
        $manifesteSg = $this->findManifeste($manifesteId);
        $oldValues = $manifesteSg->toArray();
        $manifesteSg->update($request->all());

        // Log the update
        $numero = $manifesteSg->num_manifeste ?? "N°{$manifesteSg->instance_id}";
        AuditService::log('update', "Le manifeste \"{$numero}\" a été modifié", 'ManifesteSg', $manifesteSg->instance_id, $oldValues, $manifesteSg->toArray());

        // Invalider le cache
        CacheTagger::tags(['manifestes', 'manifeste_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "Le manifeste \"{$numero}\" a été modifié avec succès",
            'data' => $manifesteSg->fresh()->toArray(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/manifestes/sg/{manifeste}",
     *     summary="Supprimer un manifeste SG",
     *     operationId="deleteManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=204, description="Manifeste supprime"),
     *     @OA\Response(response=404, description="Manifeste introuvable")
     * )
     */
    public function destroy(string $manifesteId): JsonResponse
    {
        $manifeste = $this->findManifeste($manifesteId);
        $numero = $manifeste->num_manifeste ?? "N°{$manifeste->instance_id}";
        $oldValues = $manifeste->toArray();
        
        $manifeste->delete();

        // Log the deletion
        AuditService::log('delete', "Le manifeste \"{$numero}\" a été supprimé (soft delete)", 'ManifesteSg', $manifeste->instance_id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['manifestes', 'manifeste_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "Le manifeste \"{$numero}\" a été supprimé avec succès (peut être restauré)",
            'data' => null,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}/titres-transport",
     *     summary="Lister les titres de transport d'un manifeste",
     *     operationId="manifesteTitresTransport",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des titres de transport",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function titresTransport(string $manifesteId): JsonResponse
    {
        $manifeste = $this->findManifeste($manifesteId);
        $titresTransport = $manifeste->titresTransport()->paginate();
        
        return response()->json([
            'status' => 200,
            'message' => 'Titres de transport récupérés avec succès',
            'data' => $titresTransport->items(),
            'links' => $titresTransport->linkCollection(),
            'meta' => [
                'current_page' => $titresTransport->currentPage(),
                'last_page' => $titresTransport->lastPage(),
                'per_page' => $titresTransport->perPage(),
                'total' => $titresTransport->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}/conteneurs",
     *     summary="Lister les conteneurs d'un manifeste",
     *     operationId="manifesteConteneurs",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des conteneurs",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function conteneurs(string $manifesteId): JsonResponse
    {
        $manifeste = $this->findManifeste($manifesteId);
        $numManifeste = $manifeste->num_manifeste;
        
        // Récupérer les conteneurs avec le num_manifeste
        $conteneurs = ManifesteTc::where('num_manifeste', $numManifeste)->paginate();
        
        $message = $conteneurs->total() > 0
            ? "{$conteneurs->total()} conteneur(s) trouvé(s) pour le manifeste {$numManifeste}"
            : "Aucun conteneur trouvé pour le manifeste {$numManifeste}";
        
        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $conteneurs->items(),
            'meta' => [
                'current_page' => $conteneurs->currentPage(),
                'last_page' => $conteneurs->lastPage(),
                'per_page' => $conteneurs->perPage(),
                'total' => $conteneurs->total(),
                'num_manifeste' => $numManifeste,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}/declarations",
     *     summary="Lister les declarations associees a un manifeste",
     *     operationId="manifesteDeclarations",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des declarations",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function declarations(string $manifesteId): JsonResponse
    {
        $manifeste = $this->findManifeste($manifesteId);
        $numManifeste = $manifeste->num_manifeste;
        
        // Récupérer les déclarations avec le num_manifeste
        $declarations = DeclarationSg::where('num_manifeste', $numManifeste)->paginate();
        
        $message = $declarations->total() > 0
            ? "{$declarations->total()} déclaration(s) trouvée(s) pour le manifeste {$numManifeste}"
            : "Aucune déclaration trouvée pour le manifeste {$numManifeste}";
        
        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $declarations->items(),
            'meta' => [
                'current_page' => $declarations->currentPage(),
                'last_page' => $declarations->lastPage(),
                'per_page' => $declarations->perPage(),
                'total' => $declarations->total(),
                'num_manifeste' => $numManifeste,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/manifestes/sg/{manifeste}/validate",
     *     summary="Valider la coherence d'un manifeste",
     *     operationId="validateManifeste",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultat de la validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="valid", type="boolean")
     *         )
     *     )
     * )
     */
    public function validate(string $manifesteId): JsonResponse
    {
        $manifesteSg = $this->findManifeste($manifesteId);
        $hasDeclaration = DeclarationSg::where('num_manifeste', $manifesteSg->num_manifeste)->exists();
        $numero = $manifesteSg->num_manifeste ?? "N°{$manifesteSg->instance_id}";

        // Log the validation
        AuditService::log('validate', "Validation du manifeste \"{$numero}\" - " . ($hasDeclaration ? 'Valide' : 'Invalide (aucune déclaration associée)'), 'ManifesteSg', $manifesteSg->instance_id, null, [
            'valid' => $hasDeclaration,
            'num_manifeste' => $manifesteSg->num_manifeste,
        ]);

        return response()->json([
            'status' => 200,
            'message' => $hasDeclaration 
                ? "Le manifeste \"{$numero}\" est valide (déclarations associées)"
                : "Le manifeste \"{$numero}\" est invalide - aucune déclaration associée",
            'data' => [
                'valid' => $hasDeclaration,
                'num_manifeste' => $manifesteSg->num_manifeste,
            ],
        ]);
    }

    private function findManifeste(string $identifier): ManifesteSg
    {
        $manifeste = null;

        if (Str::isUlid($identifier)) {
            $manifeste = ManifesteSg::where('ulid', $identifier)->first();
        } elseif (ctype_digit($identifier)) {
            $manifeste = ManifesteSg::where('instance_id', (int) $identifier)->first();
        } else {
            $manifeste = ManifesteSg::where('num_manifeste', $identifier)->first();
        }

        if (!$manifeste) {
            throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$identifier]);
        }

        return $manifeste;
    }
}


