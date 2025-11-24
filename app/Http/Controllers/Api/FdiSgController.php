<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fdi\StoreFdiSgRequest;
use App\Http\Requests\Fdi\UpdateFdiSgRequest;
use App\Jobs\ProcessFdiValidation;
use App\Models\FdiSg;
use App\Services\AuditService;
use App\Services\FdiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;
use App\Support\CacheTagger;

/**
 * @OA\Tag(
 *     name="FDI SG",
 *     description="Gestion des fiches de dedouanement (FDI) segment SG."
 * )
 */
class FdiSgController extends Controller
{
    public function __construct(private readonly FdiService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/fdi/sg",
     *     summary="Lister les FDI",
     *     operationId="listFdiSg",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche sur numero FDI, importateur ou fournisseur",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des FDI",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $perPage = min($request->integer('per_page', 15), 100);
        $search = $request->string('search')->toString();

        $cacheKey = sprintf('fdi_sg.index.%s.%s', $page, md5($search.$perPage));

        $data = CacheTagger::tags(['fdi_sg'])->remember($cacheKey, now()->addMinutes(5), function () use ($search, $perPage) {
            $paginator = FdiSg::query()
                ->when($search, function ($query) use ($search) {
                    $query->where('numero_fdi', 'like', "%{$search}%")
                        ->orWhere('importateur', 'like', "%{$search}%")
                        ->orWhere('fournisseur', 'like', "%{$search}%");
                })
                ->orderByDesc('date_fdi')
                ->paginate($perPage);

            return $this->formatPaginator($paginator);
        });

        return response()->json(array_merge([
            'status' => 200,
            'message' => 'FDI récupérées avec succès',
        ], $data));
    }

    /**
     * @OA\Post(
     *     path="/api/fdi/sg",
     *     summary="Creer une FDI",
     *     operationId="storeFdiSg",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="FDI creee",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(StoreFdiSgRequest $request): JsonResponse
    {
        $fdi = FdiSg::create($request->validated());
        
        // Refresh to get the generated ULID and all default values
        $fdi->refresh();

        // Log the creation
        $numero = $fdi->numero_fdi ?? "N°{$fdi->id}";
        AuditService::log('create', "La FDI \"{$numero}\" a été créée", 'FdiSg', $fdi->id, null, $fdi->toArray());

        CacheTagger::tags(['fdi_sg'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "La FDI \"{$numero}\" a été créée avec succès",
            'data' => $fdi->toArray(),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/fdi/sg/{fdi_sg}",
     *     summary="Consulter une FDI",
     *     operationId="showFdiSg",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FDI detaillee",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="FDI introuvable")
     * )
     */
    public function show(FdiSg $fdiSg): JsonResponse
    {
        $data = CacheTagger::tags(['fdi_sg'])->remember(
            "fdi_sg.show.{$fdiSg->ulid}",
            now()->addMinutes(5),
            fn () => $fdiSg->load(['articles', 'fcvr', 'declarations'])->toArray()
        );

        return response()->json([
            'status' => 200,
            'message' => 'FDI récupérée avec succès',
            'data' => $data,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/fdi/sg/{fdi_sg}",
     *     summary="Mettre a jour une FDI",
     *     operationId="updateFdiSg",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FDI mise a jour",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function update(UpdateFdiSgRequest $request, FdiSg $fdiSg): JsonResponse
    {
        $oldValues = $fdiSg->toArray();
        $payload = $request->validated();

        foreach ($payload as $attribute => $value) {
            $fdiSg->{$attribute} = $value;
        }

        $updated = $fdiSg->save();

        // Log the update
        $numero = $fdiSg->numero_fdi ?? "N°{$fdiSg->id}";
        AuditService::log('update', "La FDI \"{$numero}\" a été modifiée", 'FdiSg', $fdiSg->id, $oldValues, $fdiSg->toArray());

        CacheTagger::tags(['fdi_sg'])->flush();

        $fresh = $fdiSg->fresh();

        return response()->json([
            'status' => 200,
            'message' => "La FDI \"{$numero}\" a été modifiée avec succès",
            'data' => $fresh ? $fresh->toArray() : [],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/fdi/sg/{fdi_sg}",
     *     summary="Supprimer une FDI",
     *     operationId="deleteFdiSg",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="FDI supprimee"),
     *     @OA\Response(response=404, description="FDI introuvable")
     * )
     */
    public function destroy(FdiSg $fdiSg): JsonResponse
    {
        $numero = $fdiSg->numero_fdi ?? "N°{$fdiSg->id}";
        $oldValues = $fdiSg->toArray();
        
        $deleted = $fdiSg->getConnection()
            ->table('fdi_sg')
            ->where('id', $fdiSg->getKey())
            ->delete();
        
        // Log the deletion
        AuditService::log('delete', "La FDI \"{$numero}\" a été supprimée", 'FdiSg', $fdiSg->id, $oldValues, null);
        
        CacheTagger::tags(['fdi_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La FDI \"{$numero}\" a été supprimée avec succès",
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/fdi/sg/{fdi_sg}/articles",
     *     summary="Articles lies a une FDI",
     *     operationId="fdiArticles",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Liste des articles", @OA\JsonContent(type="object"))
     * )
     */
    public function articles(FdiSg $fdiSg): JsonResponse
    {
        $paginator = $fdiSg->articles()->paginate(25);

        return response()->json(array_merge([
            'status' => 200,
            'message' => 'Articles récupérés avec succès',
        ], $this->formatPaginator($paginator)));
    }

    /**
     * @OA\Get(
     *     path="/api/fdi/sg/{fdi_sg}/fcvr",
     *     summary="FCVR associee a une FDI",
     *     operationId="fdiFcvr",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="FCVR associee", @OA\JsonContent(type="object"))
     * )
     */
    public function fcvr(FdiSg $fdiSg): JsonResponse
    {
        $fcvr = $fdiSg->fcvr;

        if ($fcvr === null) {
            return response()->json([
                'status' => 200,
                'message' => 'Aucune FCVR associée à cette FDI',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'FCVR récupérée avec succès',
            'data' => $fcvr->toArray(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/fdi/sg/{fdi_sg}/declarations",
     *     summary="Declarations associees a une FDI",
     *     operationId="fdiDeclarations",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Liste des declarations", @OA\JsonContent(type="object"))
     * )
     */
    public function declarations(FdiSg $fdiSg): JsonResponse
    {
        $paginator = $fdiSg->declarations()->paginate(25);

        return response()->json(array_merge([
            'status' => 200,
            'message' => 'Déclarations récupérées avec succès',
        ], $this->formatPaginator($paginator)));
    }

    /**
     * @OA\Post(
     *     path="/api/fdi/sg/{fdi_sg}/compare",
     *     summary="Comparer deux FDI",
     *     operationId="compareFdi",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"secondary_ulid"},
     *             @OA\Property(property="secondary_ulid", type="string", format="uuid", description="ULID de la FDI a comparer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Resultat de la comparaison", @OA\JsonContent(type="object"))
     * )
     */
    public function compare(Request $request, FdiSg $fdiSg): JsonResponse
    {
        $validated = $request->validate([
            'secondary_ulid' => ['required', 'string', 'exists:fdi_sg,ulid'],
        ]);

        $secondary = FdiSg::where('ulid', $validated['secondary_ulid'])->firstOrFail();

        $result = $this->service->compareFdi($fdiSg, $secondary);

        return response()->json([
            'status' => 200,
            'message' => 'Comparaison effectuée avec succès',
            'data' => $result,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/fdi/sg/{fdi_sg}/validate",
     *     summary="Declencher la validation asynchrone d'une FDI",
     *     operationId="validateFdi",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Validation en file d'attente", @OA\JsonContent(type="object"))
     * )
     */
    public function validateFdi(FdiSg $fdiSg): JsonResponse
    {
        $numero = $fdiSg->numero_fdi ?? "N°{$fdiSg->id}";
        
        Bus::dispatch(new ProcessFdiValidation($fdiSg->ulid));

        // Log the validation job dispatch
        AuditService::log('validate', "Validation de la FDI \"{$numero}\" en file d'attente", 'FdiSg', $fdiSg->id, null, [
            'ulid' => $fdiSg->ulid,
            'job_dispatched' => true,
        ]);

        return response()->json([
            'status' => 200,
            'message' => "Validation de la FDI \"{$numero}\" en file d'attente",
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/fdi/sg/{fdi_sg}/calculate-droits",
     *     summary="Calculer les droits et taxes pour une FDI",
     *     operationId="calculateFdiDroits",
     *     tags={"FDI SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="fdi_sg",
     *         in="path",
     *         required=true,
     *         description="Identifiant public (ULID) de la FDI",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Montants calcules", @OA\JsonContent(type="object"))
     * )
     */
    public function calculateDroits(FdiSg $fdiSg): JsonResponse
    {
        $result = CacheTagger::tags(['fdi_sg'])->remember(
            "fdi_sg.droits.{$fdiSg->ulid}",
            now()->addMinutes(10),
            fn () => $this->service->calculateDroits($fdiSg)
        );

        return response()->json([
            'status' => 200,
            'message' => 'Droits et taxes calculés avec succès',
            'data' => $result,
        ]);
    }

    private function formatPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}

