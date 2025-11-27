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
        $annee = $request->integer('annee');
        $bureau = $request->string('bureau')->toString();
        $serie_fdi = $request->string('serie_fdi')->toString();
        $numero_serie = $request->string('numero_serie')->toString();

        $cacheKey = sprintf('fdi_sg.index.%s.%s', $page, md5($search.$perPage.$annee.$bureau.$serie_fdi.$numero_serie));

        $payload = CacheTagger::tags(['fdi_sg'])->remember($cacheKey, 300, function () use ($search, $perPage, $annee, $bureau, $serie_fdi, $numero_serie) {
            $query = FdiSg::query();

            // Recherche par composants du numéro FDI
            if ($annee) {
                $query->where('annee', $annee);
            }
            if ($bureau) {
                $query->where('bureau', 'like', "%{$bureau}%");
            }
            if ($serie_fdi) {
                $query->where('serie_fdi', $serie_fdi);
            }
            if ($numero_serie) {
                $query->where('numero_serie', 'like', "%{$numero_serie}%");
            }

            // Recherche textuelle générale
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_fdi', 'like', "%{$search}%")
                        ->orWhere('importateur', 'like', "%{$search}%")
                        ->orWhere('fournisseur', 'like', "%{$search}%")
                        ->orWhere('cc', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(annee, bureau, serie_fdi, numero_serie) LIKE ?", ["%{$search}%"]);
                });
            }

            $paginator = $query->orderByDesc('date_fdi')->paginate($perPage);

            // Ajouter le numéro FDI complet à chaque élément
            $items = $paginator->getCollection()->map(function ($fdi) {
                $data = $fdi->toArray();
                $data['numero_fdi_complet'] = $fdi->numero_fdi_complet;
                $data['identifiant'] = $fdi->identifiant;
                return $data;
            });

            $message = $paginator->total() > 0
                ? ($search || $annee || $bureau || $serie_fdi || $numero_serie
                    ? "Recherche : {$paginator->total()} FDI trouvée(s). Page {$paginator->currentPage()}/{$paginator->lastPage()}"
                    : "Liste des FDI : {$paginator->total()} enregistrement(s). Page {$paginator->currentPage()}/{$paginator->lastPage()}")
                : ($search || $annee || $bureau || $serie_fdi || $numero_serie
                    ? "Aucun résultat pour cette recherche. Essayez avec un numéro FDI, importateur ou fournisseur"
                    : "Aucune FDI enregistrée. Créez votre premier enregistrement FDI");

            return [
                'status' => 200,
                'message' => $message,
                'data' => $items->toArray(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        });

        return response()->json($payload);
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
        $identifiant = $fdi->identifiant;
        $details = [];
        if ($fdi->importateur) {
            $details[] = "Importateur : {$fdi->importateur}";
        }
        if ($fdi->date_fdi) {
            $details[] = "Date : " . $fdi->date_fdi->format('d/m/Y');
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
        
        AuditService::log('create', "La FDI \"{$identifiant}\" a été créée{$detailsStr}", 'FdiSg', $fdi->id, null, $fdi->toArray());

        CacheTagger::tags(['fdi_sg'])->flush();

        $data = $fdi->toArray();
        $data['numero_fdi_complet'] = $fdi->numero_fdi_complet;
        $data['identifiant'] = $identifiant;

        return response()->json([
            'status' => 201,
            'message' => "La FDI \"{$identifiant}\" a été créée avec succès{$detailsStr}",
            'data' => $data,
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
        $payload = CacheTagger::tags(['fdi_sg'])->remember(
            "fdi_sg.show.{$fdiSg->ulid}",
            300,
            function () use ($fdiSg) {
                $fdiSg->load(['articles', 'fcvr', 'declarations']);
                
                $identifiant = $fdiSg->identifiant;
                $numeroComplet = $fdiSg->numero_fdi_complet;
                
                // Construire un message informatif
                $details = [];
                if ($fdiSg->date_fdi) {
                    $details[] = "Date : " . $fdiSg->date_fdi->format('d/m/Y');
                }
                if ($fdiSg->importateur) {
                    $details[] = "Importateur : {$fdiSg->importateur}";
                }
                if ($fdiSg->banque) {
                    $details[] = "Banque : {$fdiSg->banque}";
                }
                if ($fdiSg->montant_domicilie_cfa) {
                    $details[] = "Montant : " . number_format($fdiSg->montant_domicilie_cfa, 0, ',', ' ') . " FCFA";
                }
                $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
                
                $message = "Détails de la FDI \"{$identifiant}\" récupérés{$detailsStr}";
                if ($fdiSg->articles()->count() > 0) {
                    $message .= " | {$fdiSg->articles()->count()} article(s)";
                }
                if ($fdiSg->declarations()->count() > 0) {
                    $message .= " | {$fdiSg->declarations()->count()} déclaration(s)";
                }

                $data = $fdiSg->toArray();
                $data['numero_fdi_complet'] = $numeroComplet;
                $data['identifiant'] = $identifiant;
                
                // Ajouter les composants du numéro FDI pour référence
                $data['composants_numero'] = [
                    'annee' => $fdiSg->annee,
                    'bureau' => $fdiSg->bureau,
                    'serie_fdi' => $fdiSg->serie_fdi,
                    'numero_serie' => $fdiSg->numero_serie,
                    'numero_fdi' => $fdiSg->numero_fdi,
                    'numero_fdi_complet' => $numeroComplet,
                ];

                return [
                    'status' => 200,
                    'message' => $message,
                    'data' => $data,
                ];
            }
        );

        return response()->json($payload);
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
        $identifiant = $fdiSg->identifiant;
        $champsModifies = array_keys($payload);
        $details = count($champsModifies) > 0 ? " | Champs modifiés : " . implode(', ', $champsModifies) : '';
        
        AuditService::log('update', "La FDI \"{$identifiant}\" a été modifiée{$details}", 'FdiSg', $fdiSg->id, $oldValues, $fdiSg->toArray());

        CacheTagger::tags(['fdi_sg'])->flush();

        $fresh = $fdiSg->fresh();
        $data = $fresh ? $fresh->toArray() : [];
        if ($fresh) {
            $data['numero_fdi_complet'] = $fresh->numero_fdi_complet;
            $data['identifiant'] = $fresh->identifiant;
        }

        return response()->json([
            'status' => 200,
            'message' => "La FDI \"{$identifiant}\" a été modifiée avec succès{$details}",
            'data' => $data,
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
        $identifiant = $fdiSg->identifiant;
        $oldValues = $fdiSg->toArray();
        
        $details = [];
        if ($fdiSg->articles()->count() > 0) {
            $details[] = "{$fdiSg->articles()->count()} article(s) associé(s)";
        }
        if ($fdiSg->declarations()->count() > 0) {
            $details[] = "{$fdiSg->declarations()->count()} déclaration(s) associée(s)";
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
        
        $deleted = $fdiSg->delete(); // Utiliser soft delete
        
        // Log the deletion
        AuditService::log('delete', "La FDI \"{$identifiant}\" a été supprimée{$detailsStr}", 'FdiSg', $fdiSg->id, $oldValues, null);
        
        CacheTagger::tags(['fdi_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La FDI \"{$identifiant}\" a été supprimée avec succès{$detailsStr}",
        ]);
    }

    /**
     * Restaurer une FDI supprimée (soft delete)
     */
    public function restore(string $ulid): JsonResponse
    {
        $fdi = FdiSg::withTrashed()->where('ulid', $ulid)->firstOrFail();

        if (! $fdi->trashed()) {
            return response()->json([
                'status' => 200,
                'message' => "La FDI \"{$fdi->identifiant}\" est déjà active. Aucune restauration nécessaire.",
                'data' => $fdi->toArray(),
            ]);
        }

        $fdi->restore();

        // Relancer une validation asynchrone pour recalculer les états métier
        Bus::dispatch(new ProcessFdiValidation($fdi->ulid));

        // Audit + cache
        AuditService::log('restore', "La FDI \"{$fdi->identifiant}\" a été restaurée et envoyée en revalidation", 'FdiSg', $fdi->id, null, [
            'articles_count' => $fdi->articles()->count(),
            'declarations_count' => $fdi->declarations()->count(),
        ]);

        CacheTagger::tags(['fdi_sg', 'fdi_validation'])->flush();

        $data = $fdi->fresh()->toArray();
        $data['numero_fdi_complet'] = $fdi->numero_fdi_complet;
        $data['identifiant'] = $fdi->identifiant;

        return response()->json([
            'status' => 200,
            'message' => "La FDI \"{$fdi->identifiant}\" a été restaurée avec succès et une revalidation asynchrone a été planifiée",
            'data' => [
                'fdi' => $data,
                'validation_job_dispatched' => true,
                'validation_result_endpoint' => "/api/fdi/sg/{$fdi->ulid}/validate/result",
            ],
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
        $identifiant = $fdiSg->identifiant;
        $paginator = $fdiSg->articles()->paginate(25);

        $message = $paginator->total() > 0
            ? "Articles de la FDI \"{$identifiant}\" : {$paginator->total()} article(s) trouvé(s). Page {$paginator->currentPage()}/{$paginator->lastPage()}"
            : "Aucun article trouvé pour la FDI \"{$identifiant}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
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
        $identifiant = $fdiSg->identifiant;
        $fcvr = $fdiSg->fcvr;

        if ($fcvr === null) {
            return response()->json([
                'status' => 200,
                'message' => "Aucune FCVR associée à la FDI \"{$identifiant}\". Créez une FCVR pour cette FDI",
                'data' => null,
            ]);
        }

        $details = [];
        if ($fcvr->numero_rfcv) {
            $details[] = "RFCV : {$fcvr->numero_rfcv}";
        }
        if ($fcvr->date_rfcv) {
            $details[] = "Date : " . $fcvr->date_rfcv->format('d/m/Y');
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';

        return response()->json([
            'status' => 200,
            'message' => "FCVR de la FDI \"{$identifiant}\" récupérée avec succès{$detailsStr}",
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
        $identifiant = $fdiSg->identifiant;
        $paginator = $fdiSg->declarations()->paginate(25);

        $message = $paginator->total() > 0
            ? "Déclarations de la FDI \"{$identifiant}\" : {$paginator->total()} déclaration(s) trouvée(s). Page {$paginator->currentPage()}/{$paginator->lastPage()}"
            : "Aucune déclaration trouvée pour la FDI \"{$identifiant}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
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

        $primaryIdentifiant = $fdiSg->identifiant;
        $secondaryIdentifiant = $secondary->identifiant;
        
        $details = [];
        if ($result['summary']['total_financial_diffs'] > 0) {
            $details[] = "{$result['summary']['total_financial_diffs']} écart(s) financier(s)";
        }
        if ($result['summary']['total_context_diffs'] > 0) {
            $details[] = "{$result['summary']['total_context_diffs']} écart(s) contextuel(s)";
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : " | Aucun écart détecté";

        return response()->json([
            'status' => 200,
            'message' => "Comparaison entre FDI \"{$primaryIdentifiant}\" et \"{$secondaryIdentifiant}\" effectuée avec succès{$detailsStr}",
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
    public function validateFdi(Request $request, FdiSg $fdiSg): JsonResponse
    {
        $identifiant = $fdiSg->identifiant;
        $async = $request->boolean('async', false);

        // Si async, dispatcher le job
        if ($async) {
            Bus::dispatch(new ProcessFdiValidation($fdiSg->ulid));

            $details = [];
            if ($fdiSg->derniere_operation) {
                $details[] = "Dernière opération : {$fdiSg->derniere_operation}";
            }
            if ($fdiSg->articles()->count() > 0) {
                $details[] = "{$fdiSg->articles()->count()} article(s) à valider";
            }
            $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
            
            AuditService::log('validate', "Validation asynchrone de la FDI \"{$identifiant}\" en file d'attente{$detailsStr}", 'FdiSg', $fdiSg->id, null, [
                'ulid' => $fdiSg->ulid,
                'job_dispatched' => true,
            ]);

            return response()->json([
                'status' => 200,
                'message' => "Validation asynchrone de la FDI \"{$identifiant}\" en file d'attente{$detailsStr}. Le résultat sera disponible prochainement",
                'data' => [
                    'ulid' => $fdiSg->ulid,
                    'job_dispatched' => true,
                    'result_endpoint' => "/api/fdi/sg/{$fdiSg->ulid}/validate/result",
                ],
            ]);
        }

        // Validation synchrone
        $result = $this->service->validateFdi($fdiSg);

        $details = [];
        if ($result['valid']) {
            $details[] = "FDI valide";
            if ($result['complete']) {
                $details[] = "Tous les champs requis et recommandés sont présents";
            } else {
                $details[] = "{$result['summary']['recommended_fields_present']}/{$result['summary']['recommended_fields_total']} champs recommandés présents";
            }
        } else {
            $details[] = count($result['missing_required']) . " champ(s) obligatoire(s) manquant(s)";
        }
        $details[] = "{$result['summary']['completion_percentage']}% de complétude";
        $detailsStr = " | " . implode(' | ', $details);

        AuditService::log('validate', "Validation de la FDI \"{$identifiant}\" effectuée{$detailsStr}", 'FdiSg', $fdiSg->id, null, $result);

        return response()->json([
            'status' => 200,
            'message' => "Validation de la FDI \"{$identifiant}\" effectuée{$detailsStr}",
            'data' => $result,
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
        $identifiant = $fdiSg->identifiant;
        
        $result = CacheTagger::tags(['fdi_sg'])->remember(
            "fdi_sg.droits.{$fdiSg->ulid}",
            300,
            fn () => $this->service->calculateDroits($fdiSg)
        );

        $details = [];
        if ($result['base_taxable'] > 0) {
            $details[] = "Base taxable : " . number_format($result['base_taxable'], 0, ',', ' ') . " FCFA";
        }
        if ($result['droits_douane'] > 0) {
            $details[] = "Droits : " . number_format($result['droits_douane'], 0, ',', ' ') . " FCFA";
        }
        if ($result['tva'] > 0) {
            $details[] = "TVA : " . number_format($result['tva'], 0, ',', ' ') . " FCFA";
        }
        if ($result['total_droits_taxes'] > 0) {
            $details[] = "Total : " . number_format($result['total_droits_taxes'], 0, ',', ' ') . " FCFA";
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';

        return response()->json([
            'status' => 200,
            'message' => "Droits et taxes calculés pour la FDI \"{$identifiant}\" avec succès{$detailsStr}",
            'data' => $result,
        ]);
    }

}

