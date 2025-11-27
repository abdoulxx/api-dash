<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CalculateDeclarationTaxes;
use App\Models\DeclarationArticle;
use App\Models\DeclarationSg;
use App\Models\DeclarationTc;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeclarationSgController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'declarations:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['declarations'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $search = $request->string('search')->toString();

            $query = DeclarationSg::query()
                ->when($search, function ($builder) use ($search) {
                    // Recherche sur le numéro complet (declaration)
                    $builder->where(function ($q) use ($search) {
                        $q->where('declaration', 'ilike', "%{$search}%")
                            // Recherche sur la concaténation ANNEE || BUREAU || TYPE || NUMERO_SEQUENTIEL
                            ->orWhereRaw("(COALESCE(annee::text, '') || COALESCE(bureau, '') || 'C' || COALESCE(SUBSTRING(declaration FROM '[0-9]+$'), '')) ILIKE ?", ["%{$search}%"])
                            // Recherche sur les autres champs
                            ->orWhere('num_manifeste', 'ilike', "%{$search}%")
                            ->orWhere('num_fdi', 'ilike', "%{$search}%")
                            ->orWhere('num_bl', 'ilike', "%{$search}%")
                            ->orWhere('importateur', 'ilike', "%{$search}%")
                            ->orWhere('exportateur', 'ilike', "%{$search}%")
                            ->orWhere('declarant', 'ilike', "%{$search}%")
                            ->orWhere('quittance', 'ilike', "%{$search}%");
                    });
                })
                ->latest('date_declaration');

            $paginator = $query->paginate($perPage);

            $items = $paginator->getCollection()
                ->map(function (DeclarationSg $declaration) {
                    $data = $declaration->toArray();
                    $data['numero_declaration_complet'] = $declaration->numero_declaration_complet;
                    $data['identifiant'] = $declaration->identifiant;
                    $data['message_resume'] = sprintf(
                        '%s | Bureau : %s | Importateur : %s | Valeur CAF : %s %s',
                        $declaration->numero_declaration_complet ?? $declaration->identifiant,
                        $declaration->nom_bureau ?? $declaration->bureau ?? 'N/A',
                        $declaration->importateur ?? 'N/A',
                        number_format((float) ($declaration->valeur_caf_declaration ?? 0), 2, ',', ' '),
                        $declaration->devise ?? 'XOF'
                    );
                    return $data;
                })
                ->values()
                ->toArray();

            $lastPage = max($paginator->lastPage(), 1);
            $message = $paginator->total() > 0
                ? ($search
                    ? sprintf("Recherche \"%s\" : %d déclaration(s) trouvée(s). Page %d/%d", $search, $paginator->total(), $paginator->currentPage(), $lastPage)
                    : sprintf("Liste des déclarations : %d enregistrement(s). Page %d/%d", $paginator->total(), $paginator->currentPage(), $lastPage))
                : ($search
                    ? sprintf("Aucun résultat pour \"%s\". Essayez avec un numéro de déclaration (ex: 2020CIAB1C1), manifeste, FDI ou importateur", $search)
                    : "Aucune déclaration enregistrée pour le moment");

            return [
                'status' => 200,
                'message' => $message,
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $declaration = DeclarationSg::create($validated);
        $declaration->refresh();

        // Log the creation
        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        AuditService::log('create', "La déclaration \"{$numero}\" a été créée", 'DeclarationSg', $declaration->id, null, $declaration->toArray());

        // Invalider le cache
        CacheTagger::tags(['declarations'])->flush();

        $data = $declaration->toArray();
        $data['numero_declaration_complet'] = $declaration->numero_declaration_complet;
        $data['identifiant'] = $declaration->identifiant;
        $data['message_resume'] = sprintf(
            '%s | Bureau : %s | Importateur : %s | Valeur CAF : %s %s',
            $declaration->numero_declaration_complet ?? $declaration->identifiant,
            $declaration->nom_bureau ?? $declaration->bureau ?? 'N/A',
            $declaration->importateur ?? 'N/A',
            number_format((float) ($declaration->valeur_caf_declaration ?? 0), 2, ',', ' '),
            $declaration->devise ?? 'XOF'
        );

        return response()->json([
            'status' => 201,
            'message' => "La déclaration \"{$numero}\" a été créée avec succès | Bureau : " . ($declaration->nom_bureau ?? $declaration->bureau ?? 'N/A') . " | Importateur : " . ($declaration->importateur ?? 'Non renseigné'),
            'data' => $data,
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $cacheKey = "declarations:show:{$ulid}";

        $payload = CacheTagger::tags(['declarations'])->remember($cacheKey, 300, function () use ($ulid) {
            $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
            
            // Charger les relations
            $declaration->load([
                'manifeste',
                'fdi',
                'fcvr',
                'articles' => function ($query) {
                    $query->limit(100); // Limiter pour éviter les réponses trop lourdes
                }
            ]);

            // Compter les conteneurs
            $conteneursCount = DeclarationTc::where('num_manifeste', $declaration->num_manifeste)
                ->orWhere('instanceid', $declaration->instanceid)
                ->count();

            // Préparer les données de base
            $data = $declaration->toArray();
            $data['numero_declaration_complet'] = $declaration->numero_declaration_complet;
            $data['identifiant'] = $declaration->identifiant;
            $data['message_resume'] = sprintf(
                '%s | Bureau : %s | Importateur : %s | Valeur CAF : %s %s | Articles : %d | Conteneurs : %d',
                $declaration->numero_declaration_complet ?? $declaration->identifiant,
                $declaration->nom_bureau ?? $declaration->bureau ?? 'N/A',
                $declaration->importateur ?? 'N/A',
                number_format((float) ($declaration->valeur_caf_declaration ?? 0), 2, ',', ' '),
                $declaration->devise ?? 'XOF',
                $declaration->articles()->count(),
                $conteneursCount
            );

            // Préparer les relations avec identifiants
            $relations = [];

            // Manifeste
            if ($declaration->manifeste) {
                $relations['manifeste'] = [
                    'ulid' => $declaration->manifeste->ulid ?? null,
                    'num_manifeste' => $declaration->manifeste->num_manifeste ?? null,
                    'identifiant' => $declaration->manifeste->num_manifeste ?? 'N/A',
                ];
            }

            // FDI
            if ($declaration->fdi) {
                $relations['fdi'] = [
                    'ulid' => $declaration->fdi->ulid ?? null,
                    'numero_fdi' => $declaration->fdi->numero_fdi ?? null,
                    'numero_fdi_complet' => $declaration->fdi->numero_fdi_complet ?? null,
                    'identifiant' => $declaration->fdi->identifiant ?? 'N/A',
                ];
            }

            // FCVR
            if ($declaration->fcvr) {
                $relations['fcvr'] = [
                    'ulid' => $declaration->fcvr->ulid ?? null,
                    'num_rfcv' => $declaration->fcvr->num_rfcv ?? null,
                    'numero_fcvr_complet' => $declaration->fcvr->numero_fcvr_complet ?? null,
                    'identifiant' => $declaration->fcvr->identifiant ?? 'N/A',
                ];
            }

            // Articles (avec données réelles)
            $articlesCount = $declaration->articles()->count();
            $articles = $declaration->articles()->limit(10)->get()->map(function ($article) {
                return [
                    'id' => $article->id,
                    'numero_article' => $article->numero_article,
                    'postar' => $article->postar,
                    'libelle_postar' => $article->libelle_postar,
                    'nbre_colis' => $article->nbre_colis,
                    'poids_net' => $article->poids_net,
                    'poids_brut' => $article->poids_brut,
                    'valcaf' => $article->valcaf,
                    'valfob' => $article->valfob,
                    'droits_taxes' => $article->droits_taxes,
                ];
            })->toArray();
            
            $relations['articles'] = [
                'count' => $articlesCount,
                'has_articles' => $articlesCount > 0,
                'url' => "/api/declarations/sg/{$ulid}/articles",
                'items' => $articles,
                'message' => $articlesCount > 0 
                    ? "{$articlesCount} article(s) associé(s) à cette déclaration" 
                    : "Aucun article associé à cette déclaration",
            ];

            // Conteneurs (résumé)
            $relations['conteneurs'] = [
                'count' => $conteneursCount,
                'has_conteneurs' => $conteneursCount > 0,
                'url' => "/api/declarations/sg/{$ulid}/conteneurs",
            ];

            $data['relations'] = $relations;
            
            // Ajouter les articles directement dans data
            $data['articles'] = $articles;
            $data['articles_count'] = $articlesCount;
            $data['has_articles'] = $articlesCount > 0;

            // Message enrichi
            $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
            $message = sprintf(
                "Détails de la déclaration \"%s\" récupérés avec succès | Bureau : %s | Importateur : %s | %d article(s) | %d conteneur(s)",
                $numero,
                $declaration->nom_bureau ?? $declaration->bureau ?? 'N/A',
                $declaration->importateur ?? 'N/A',
                $articlesCount,
                $conteneursCount
            );

            return [
                'status' => 200,
                'message' => $message,
                'data' => $data,
            ];
        });

        return response()->json($payload);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        
        $validated = $request->validate($this->rules(update: true));
        $oldValues = $declaration->toArray();

        $declaration->update($validated);
        $declaration->refresh();

        // Log the update
        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        
        // Détecter les champs modifiés
        $modifiedFields = [];
        foreach ($validated as $key => $value) {
            if (isset($oldValues[$key]) && $oldValues[$key] != $value) {
                $modifiedFields[] = $key;
            }
        }
        
        $fieldsMessage = !empty($modifiedFields) 
            ? " | Champs modifiés : " . implode(', ', $modifiedFields)
            : "";
        
        AuditService::log('update', "La déclaration \"{$numero}\" a été mise à jour", 'DeclarationSg', $declaration->id, $oldValues, $declaration->toArray());

        // Invalider le cache
        CacheTagger::tags(['declarations'])->flush();

        $data = $declaration->toArray();
        $data['numero_declaration_complet'] = $declaration->numero_declaration_complet;
        $data['identifiant'] = $declaration->identifiant;
        $data['message_resume'] = sprintf(
            '%s | Bureau : %s | Importateur : %s | Valeur CAF : %s %s',
            $declaration->numero_declaration_complet ?? $declaration->identifiant,
            $declaration->nom_bureau ?? $declaration->bureau ?? 'N/A',
            $declaration->importateur ?? 'N/A',
            number_format((float) ($declaration->valeur_caf_declaration ?? 0), 2, ',', ' '),
            $declaration->devise ?? 'XOF'
        );

        return response()->json([
            'status' => 200,
            'message' => "La déclaration \"{$numero}\" a été mise à jour avec succès{$fieldsMessage}",
            'data' => $data,
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        $oldValues = $declaration->toArray();
        
        $declaration->delete();

        // Log the deletion
        AuditService::log('delete', "La déclaration \"{$numero}\" a été supprimée", 'DeclarationSg', $declaration->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['declarations'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La déclaration \"{$numero}\" a été supprimée avec succès",
            'data' => null,
        ]);
    }

    public function manifeste(string $ulid): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        $manifeste = $declaration->manifeste;
        
        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        $message = $manifeste
            ? "Manifeste lié à la déclaration \"{$numero}\" récupéré avec succès"
            : "Aucun manifeste lié à la déclaration \"{$numero}\"";

        $data = null;
        if ($manifeste) {
            $data = $manifeste->toArray();
            $data['declaration'] = [
                'ulid' => $declaration->ulid,
                'identifiant' => $declaration->identifiant,
                'numero_declaration_complet' => $declaration->numero_declaration_complet,
            ];
        }

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function articles(string $ulid, Request $request): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        $perPage = min($request->integer('per_page', 25), 100);
        $paginator = $declaration->articles()->paginate($perPage);

        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        $message = $paginator->total() > 0
            ? "{$paginator->total()} article(s) trouvé(s) pour la déclaration \"{$numero}\" | Bureau : " . ($declaration->nom_bureau ?? $declaration->bureau ?? 'N/A') . " | Importateur : " . ($declaration->importateur ?? 'N/A')
            : "Aucun article trouvé pour la déclaration \"{$numero}\". Vous pouvez ajouter des articles via l'endpoint POST /api/declarations/articles";

        $items = collect($paginator->items())->map(function ($article) use ($declaration) {
            $articleArray = is_array($article) ? $article : $article->toArray();
            $articleArray['declaration'] = [
                'ulid' => $declaration->ulid,
                'identifiant' => $declaration->identifiant,
                'numero_declaration_complet' => $declaration->numero_declaration_complet,
                'annee' => $declaration->annee,
                'bureau' => $declaration->bureau,
                'nom_bureau' => $declaration->nom_bureau,
                'date_declaration' => $declaration->date_declaration,
                'importateur' => $declaration->importateur,
                'valeur_caf_declaration' => $declaration->valeur_caf_declaration,
                'valeur_fob_declaration' => $declaration->valeur_fob_declaration,
            ];
            return $articleArray;
        })->toArray();

        // Informations de la déclaration pour le contexte
        $declarationInfo = [
            'ulid' => $declaration->ulid,
            'identifiant' => $declaration->identifiant,
            'numero_declaration_complet' => $declaration->numero_declaration_complet,
            'annee' => $declaration->annee,
            'bureau' => $declaration->bureau,
            'nom_bureau' => $declaration->nom_bureau,
            'date_declaration' => $declaration->date_declaration,
            'importateur' => $declaration->importateur,
            'exportateur' => $declaration->exportateur,
            'valeur_caf_declaration' => $declaration->valeur_caf_declaration,
            'valeur_fob_declaration' => $declaration->valeur_fob_declaration,
            'nbre_total_article' => $declaration->nbre_total_article,
        ];

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $items,
            'declaration' => $declarationInfo,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function conteneurs(string $ulid, Request $request): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        $perPage = min($request->integer('per_page', 25), 100);
        $query = DeclarationTc::query()
            ->where('num_manifeste', $declaration->num_manifeste)
            ->orWhere('instanceid', $declaration->instanceid);

        $paginator = $query->paginate($perPage);

        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        $message = $paginator->total() > 0
            ? "{$paginator->total()} conteneur(s) trouvé(s) pour la déclaration \"{$numero}\""
            : "Aucun conteneur trouvé pour la déclaration \"{$numero}\"";

        $items = collect($paginator->items())->map(function ($conteneur) use ($declaration) {
            $conteneurArray = is_array($conteneur) ? $conteneur : $conteneur->toArray();
            $conteneurArray['declaration_identifiant'] = $declaration->identifiant;
            $conteneurArray['numero_declaration_complet'] = $declaration->numero_declaration_complet;
            return $conteneurArray;
        })->toArray();

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function validate(string $ulid, Request $request = null): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        
        // Champs obligatoires pour la validation
        $requiredFields = [
            'declaration' => 'Numéro de déclaration',
            'num_manifeste' => 'Numéro de manifeste',
            'num_fdi' => 'Numéro FDI',
        ];
        
        // Champs recommandés
        $recommendedFields = [
            'date_declaration' => 'Date de déclaration',
            'num_bl' => 'Numéro BL',
            'importateur' => 'Importateur',
            'exportateur' => 'Exportateur',
            'declarant' => 'Déclarant',
            'bureau' => 'Bureau',
            'devise' => 'Devise',
            'valeur_caf_declaration' => 'Valeur CAF',
            'valeur_fob_declaration' => 'Valeur FOB',
        ];
        
        // Vérifier les champs obligatoires
        $missing = collect($requiredFields)
            ->filter(fn ($label, $field) => blank($declaration->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        // Vérifier les champs recommandés
        $missingRecommended = collect($recommendedFields)
            ->filter(fn ($label, $field) => blank($declaration->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        $isValid = $missing->isEmpty();
        $isComplete = $missing->isEmpty() && $missingRecommended->isEmpty();
        
        // Compter les articles et conteneurs associés
        $articlesCount = $declaration->articles()->count();
        $conteneursCount = DeclarationTc::where('num_manifeste', $declaration->num_manifeste)
            ->orWhere('instanceid', $declaration->instanceid)
            ->count();
        
        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        $completionRate = count($requiredFields) > 0 
            ? round((count($requiredFields) - $missing->count()) / count($requiredFields) * 100, 1)
            : 100;
        
        $message = $isValid
            ? ($isComplete 
                ? "La déclaration \"{$numero}\" est valide et complète ({$completionRate}% complète)"
                : "La déclaration \"{$numero}\" est valide mais des informations recommandées sont manquantes ({$completionRate}% complète)")
            : "La déclaration \"{$numero}\" est invalide : " . ($missing->count() === 1 ? "le champ \"{$missing->first()['label']}\" est manquant" : "plusieurs champs obligatoires sont manquants") . " ({$completionRate}% complète)";

        // Log the validation
        AuditService::log('validate', "Validation de la déclaration \"{$numero}\" - " . ($isValid ? ($isComplete ? 'Valide et complète' : 'Valide mais incomplète') : 'Invalide'), 'DeclarationSg', $declaration->id, null, [
            'valid' => $isValid,
            'complete' => $isComplete,
            'completion_rate' => $completionRate,
            'missing_fields' => $missing->toArray(),
            'articles_count' => $articlesCount,
            'conteneurs_count' => $conteneursCount,
        ]);

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'valid' => $isValid,
                'complete' => $isComplete,
                'completion_rate' => $completionRate,
                'missing_fields' => $missing->toArray(),
                'missing_recommended_fields' => $missingRecommended->toArray(),
                'validation_summary' => [
                    'total_required_fields' => count($requiredFields),
                    'filled_required_fields' => count($requiredFields) - $missing->count(),
                    'total_recommended_fields' => count($recommendedFields),
                    'filled_recommended_fields' => count($recommendedFields) - $missingRecommended->count(),
                    'completion_rate' => $completionRate,
                ],
                'declaration_info' => [
                    'id' => $declaration->id,
                    'ulid' => $declaration->ulid,
                    'declaration' => $declaration->declaration,
                    'numero_declaration_complet' => $declaration->numero_declaration_complet,
                    'identifiant' => $declaration->identifiant,
                    'annee' => $declaration->annee,
                    'bureau' => $declaration->bureau,
                    'nom_bureau' => $declaration->nom_bureau,
                    'num_manifeste' => $declaration->num_manifeste,
                    'num_fdi' => $declaration->num_fdi,
                    'date_declaration' => $declaration->date_declaration,
                    'articles_count' => $articlesCount,
                    'conteneurs_count' => $conteneursCount,
                    'has_articles' => $articlesCount > 0,
                    'has_conteneurs' => $conteneursCount > 0,
                ],
            ],
        ]);
    }

    public function calculateTaxes(string $ulid, Request $request): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        
        // Option pour dispatcher en queue (utile pour les déclarations avec beaucoup d'articles)
        $useQueue = $request->boolean('async', false);
        
        if ($useQueue) {
            $cacheKey = sprintf('declaration:taxes:%s:%s', $ulid, Str::random(12));
            
            Bus::dispatch(new CalculateDeclarationTaxes($ulid, $cacheKey));
            
            $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
            
            return response()->json([
                'status' => 202,
                'message' => "Calcul des taxes en cours de traitement pour la déclaration \"{$numero}\"",
                'data' => [
                    'queued' => true,
                    'reference' => $cacheKey,
                    'declaration' => $declaration->declaration,
                    'numero_declaration_complet' => $declaration->numero_declaration_complet,
                    'identifiant' => $declaration->identifiant,
                    'check_url' => "/api/declarations/sg/{$ulid}/calculate-taxes/result?reference={$cacheKey}",
                ],
            ], 202);
        }
        
        // Exécution synchrone (par défaut)
        $totals = DeclarationArticle::query()
            ->where('declaration', $declaration->declaration)
            ->selectRaw('SUM(valcaf) as caf, SUM(valfob) as fob, SUM(droits_taxes) as taxes')
            ->first();

        $articlesCount = DeclarationArticle::where('declaration', $declaration->declaration)->count();

        $numero = $declaration->numero_declaration_complet ?? $declaration->identifiant;
        
        // Log the tax calculation
        AuditService::log('calculate-taxes', "Calcul des taxes effectué pour la déclaration \"{$numero}\" ({$articlesCount} article(s))", 'DeclarationSg', $declaration->id, null, [
            'articles_count' => $articlesCount,
            'totals' => [
                'caf' => (float) ($totals->caf ?? 0),
                'fob' => (float) ($totals->fob ?? 0),
                'taxes' => (float) ($totals->taxes ?? 0),
            ],
        ]);

        return response()->json([
            'status' => 200,
            'message' => "Calcul des taxes effectué pour la déclaration \"{$numero}\" ({$articlesCount} article(s))",
            'data' => [
                'declaration' => $declaration->declaration,
                'numero_declaration_complet' => $declaration->numero_declaration_complet,
                'identifiant' => $declaration->identifiant,
                'declaration_ulid' => $declaration->ulid,
                'articles_count' => $articlesCount,
                'totals' => [
                    'caf' => (float) ($totals->caf ?? 0),
                    'fob' => (float) ($totals->fob ?? 0),
                    'taxes' => (float) ($totals->taxes ?? 0),
                ],
                'totals_formatted' => [
                    'caf' => number_format((float) ($totals->caf ?? 0), 2, ',', ' ') . ' XOF',
                    'fob' => number_format((float) ($totals->fob ?? 0), 2, ',', ' ') . ' XOF',
                    'taxes' => number_format((float) ($totals->taxes ?? 0), 2, ',', ' ') . ' XOF',
                ],
            ],
        ]);
    }
    
    public function calculateTaxesResult(Request $request): JsonResponse
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return response()->json([
                'status' => 400,
                'message' => 'Le paramètre "reference" est requis',
                'data' => null,
            ], 400);
        }
        
        $result = CacheTagger::tags(['declarations'])->get($reference);
        
        if (!$result) {
            return response()->json([
                'status' => 202,
                'message' => 'Le calcul est encore en cours de traitement',
                'data' => [
                    'reference' => $reference,
                    'status' => 'processing',
                ],
            ], 202);
        }
        
        return response()->json([
            'status' => 200,
            'message' => 'Résultat du calcul des taxes récupéré avec succès',
            'data' => $result,
        ]);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new DeclarationSg())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['instanceid'] = $update ? 'sometimes|integer' : 'required|integer';
        $rules['declaration'] = $update ? 'sometimes|string|max:240' : 'required|string|max:240';

        return $rules;
    }

    private function formatPaginator($paginator): array
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









