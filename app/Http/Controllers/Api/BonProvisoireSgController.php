<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ValidateBonProvisoire;
use App\Models\BonProvisoireArticle;
use App\Models\BonProvisoireSg;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonProvisoireSgController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $search = $request->string('search')->toString();
        $page = $request->integer('page', 1);

        // Valider que la page demandée est valide
        if ($page < 1) {
            return response()->json([
                'status' => 400,
                'message' => 'Le numéro de page doit être supérieur ou égal à 1',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
                'links' => [
                    'first' => null,
                    'last' => null,
                    'prev' => null,
                    'next' => null,
                ],
            ], 400);
        }

        $cacheKey = sprintf('bons-provisoires.index.%s.%s', $page, md5($search.$perPage));

        $payload = CacheTagger::tags(['bons-provisoires'])->remember($cacheKey, 300, function () use ($search, $perPage, $page) {
            $query = BonProvisoireSg::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_bon_provisoire', 'like', "%{$search}%")
                        ->orWhere('num_lta', 'like', "%{$search}%")
                        ->orWhere('nom_importateur', 'like', "%{$search}%")
                        ->orWhere('nom_fournisseur', 'like', "%{$search}%")
                        ->orWhere('ncc', 'like', "%{$search}%")
                        // Recherche par composants du numéro (ANNEE || BUREAU || SERIE_BP || NUM_SERIE_BP)
                        ->orWhereRaw("CONCAT(COALESCE(annee::text, ''), COALESCE(bureau, ''), COALESCE(serie_bp, ''), COALESCE(num_serie_bp, '')) like ?", ["%{$search}%"]);
                });
            }

            $paginator = $query->latest('date_bp')->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()
                ->map(function (BonProvisoireSg $bon) {
                    $data = $bon->toArray();
                    $data['numero_bon_provisoire_complet'] = $bon->numero_bon_provisoire_complet;
                    $data['identifiant'] = $bon->identifiant;
                    $data['message_resume'] = sprintf(
                        '%s | Importateur : %s | LTA : %s | Expiration : %s',
                        $bon->identifiant,
                        $bon->nom_importateur ?? 'N/A',
                        $bon->num_lta ?? 'N/A',
                        $bon->date_expiration ? $bon->date_expiration->format('d/m/Y') : 'N/A'
                    );
                    return $data;
                })
                ->values()
                ->toArray();

            // Construire les liens de pagination en utilisant le chemin relatif
            $basePath = '/api/bons-provisoires/sg';
            $queryParams = [];
            if ($search) {
                $queryParams['search'] = $search;
            }
            if ($perPage != 15) {
                $queryParams['per_page'] = $perPage;
            }

            $buildUrl = function ($pageNum) use ($basePath, $queryParams) {
                $params = array_merge($queryParams, ['page' => $pageNum]);
                return $basePath . '?' . http_build_query($params);
            };

            return [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'first' => $buildUrl(1),
                    'last' => $buildUrl($paginator->lastPage()),
                    'prev' => $paginator->currentPage() > 1 ? $buildUrl($paginator->currentPage() - 1) : null,
                    'next' => $paginator->currentPage() < $paginator->lastPage() ? $buildUrl($paginator->currentPage() + 1) : null,
                ],
                'items' => $items,
            ];
        });

        $meta = $payload['meta'];
        $links = $payload['links'];

        // Vérifier si la page demandée existe
        if ($page > $meta['last_page'] && $meta['last_page'] > 0) {
            return response()->json([
                'status' => 404,
                'message' => sprintf(
                    'La page %d n\'existe pas. La dernière page disponible est la page %d (sur un total de %d résultat(s))',
                    $page,
                    $meta['last_page'],
                    $meta['total']
                ),
                'data' => [],
                'meta' => $meta,
                'links' => $links,
            ], 404);
        }

        $lastPageDisplay = max($meta['last_page'], 1);
        $message = $meta['total'] > 0
            ? ($search
                ? sprintf('Recherche "%s" : %d bon(s) provisoire(s) trouvé(s). Page %d/%d', $search, $meta['total'], $meta['current_page'], $lastPageDisplay)
                : sprintf('Liste des bons provisoires : %d enregistrement(s). Page %d/%d', $meta['total'], $meta['current_page'], $lastPageDisplay))
            : ($search
                ? sprintf('Aucun résultat pour "%s". Essayez avec un numéro complet (ANNEE+BUREAU+SERIE+NUM), LTA ou importateur', $search)
                : 'Aucun bon provisoire enregistré. Créez votre premier bon provisoire');

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $payload['items'],
            'meta' => $meta,
            'links' => $links,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Validation personnalisée : numero_bon_provisoire est requis SAUF si tous les composants sont fournis
        $validated = $request->validate($this->rules());
        
        // Vérifier si numero_bon_provisoire est manquant mais que les composants sont fournis
        $hasComponents = !empty($validated['annee']) && 
                        !empty($validated['bureau']) && 
                        !empty($validated['serie_bp']) && 
                        !empty($validated['num_serie_bp']);
        
        if (empty($validated['numero_bon_provisoire']) && !$hasComponents) {
            return response()->json([
                'status' => 422,
                'message' => 'Le champ "numero_bon_provisoire" est requis, ou vous devez fournir tous les composants (annee, bureau, serie_bp, num_serie_bp)',
                'errors' => [
                    'numero_bon_provisoire' => [
                        'Le champ "numero_bon_provisoire" est requis, ou vous devez fournir tous les composants (annee, bureau, serie_bp, num_serie_bp)'
                    ]
                ],
                'data' => null,
            ], 422);
        }

        // Générer automatiquement le numéro complet si les composants sont fournis mais pas le numéro
        if (empty($validated['numero_bon_provisoire']) && $hasComponents) {
            $validated['numero_bon_provisoire'] = sprintf(
                '%s%s%s%s',
                $validated['annee'],
                $validated['bureau'],
                $validated['serie_bp'],
                $validated['num_serie_bp']
            );
        }

        // S'assurer que l'ID n'est pas dans les données validées (il sera généré automatiquement)
        unset($validated['id']);

        $bon = BonProvisoireSg::create($validated);
        $bon->refresh();

        // Log the creation
        $numero = $bon->identifiant;
        AuditService::log('create', "Le bon provisoire \"{$numero}\" a été créé", 'BonProvisoireSg', $bon->id, null, $bon->toArray());

        // Invalider le cache
        CacheTagger::tags(['bons-provisoires'])->flush();

        $data = $bon->toArray();
        $data['numero_bon_provisoire_complet'] = $bon->numero_bon_provisoire_complet;
        $data['identifiant'] = $bon->identifiant;

        return response()->json([
            'status' => 201,
            'message' => "Le bon provisoire \"{$numero}\" ({$bon->numero_bon_provisoire_complet}) a été créé avec succès",
            'data' => $data,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        // Rechercher par ULID ou ID
        if (Str::isUlid($id)) {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        } elseif (is_numeric($id)) {
            $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        } else {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        }

        $cacheKey = sprintf('bons-provisoires.show.%s', $bon->ulid ?? $bon->id);

        $payload = CacheTagger::tags(['bons-provisoires'])->remember($cacheKey, 300, function () use ($bon) {
            $bon->load('articles');

            $data = $bon->toArray();
            $data['numero_bon_provisoire_complet'] = $bon->numero_bon_provisoire_complet;
            $data['identifiant'] = $bon->identifiant;
            $data['message_resume'] = sprintf(
                '%s | Importateur : %s | LTA : %s | Expiration : %s',
                $bon->identifiant,
                $bon->nom_importateur ?? 'N/A',
                $bon->num_lta ?? 'N/A',
                $bon->date_expiration ? $bon->date_expiration->format('d/m/Y') : 'N/A'
            );

            $details = [];
            $details[] = 'Importateur : ' . ($data['nom_importateur'] ?? 'N/A');
            if ($data['num_lta']) {
                $details[] = 'LTA : ' . $data['num_lta'];
            }
            if ($data['date_expiration']) {
                $details[] = 'Expiration : ' . $bon->date_expiration->format('d/m/Y');
            }
            if ($data['delai_jours']) {
                $details[] = 'Délai : ' . $data['delai_jours'] . ' jour(s)';
            }
            $detailsStr = $details ? ' | ' . implode(' | ', $details) : '';

            return [
                'data' => $data,
                'details' => $detailsStr,
            ];
        });

        if (!is_array($payload) || !isset($payload['data'])) {
            $data = $bon->toArray();
            $data['numero_bon_provisoire_complet'] = $bon->numero_bon_provisoire_complet;
            $data['identifiant'] = $bon->identifiant;
            $payload = [
                'data' => $data,
                'details' => '',
            ];
            CacheTagger::tags(['bons-provisoires'])->put($cacheKey, $payload, now()->addMinutes(5));
        }

        $bonData = $payload['data'];
        $details = $payload['details'] ?? '';

        return response()->json([
            'status' => 200,
            'message' => "Bon provisoire \"{$bonData['identifiant']}\" récupéré avec succès{$details}",
            'data' => $bonData,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        // Rechercher par ULID ou ID
        if (Str::isUlid($id)) {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        } elseif (is_numeric($id)) {
            $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        } else {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        }
        
        $validated = $request->validate($this->rules(true));
        $oldValues = $bon->toArray();

        // Générer automatiquement le numéro complet si les composants sont modifiés
        if (empty($validated['numero_bon_provisoire']) && 
            (!empty($validated['annee']) || !empty($validated['bureau']) || !empty($validated['serie_bp']) || !empty($validated['num_serie_bp']))) {
            $annee = $validated['annee'] ?? $bon->annee;
            $bureau = $validated['bureau'] ?? $bon->bureau;
            $serie = $validated['serie_bp'] ?? $bon->serie_bp;
            $numSerie = $validated['num_serie_bp'] ?? $bon->num_serie_bp;
            
            if ($annee && $bureau && $serie && $numSerie) {
                $validated['numero_bon_provisoire'] = sprintf('%s%s%s%s', $annee, $bureau, $serie, $numSerie);
            }
        }

        $bon->fill($validated);
        $dirtyFields = array_keys($bon->getDirty());
        $bon->save();
        $bon->refresh();

        // Log the update
        $numero = $bon->identifiant;
        $details = $dirtyFields
            ? 'Champs modifiés : ' . implode(', ', $dirtyFields)
            : 'Aucun changement détecté';
        
        AuditService::log('update', "Le bon provisoire \"{$numero}\" a été modifié | {$details}", 'BonProvisoireSg', $bon->id, $oldValues, $bon->toArray());

        // Invalider le cache
        CacheTagger::tags(['bons-provisoires'])->flush();

        $data = $bon->toArray();
        $data['numero_bon_provisoire_complet'] = $bon->numero_bon_provisoire_complet;
        $data['identifiant'] = $bon->identifiant;

        return response()->json([
            'status' => 200,
            'message' => "Le bon provisoire \"{$numero}\" a été mis à jour avec succès | {$details}",
            'data' => $data,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        $numero = $bon->numero_bon_provisoire;
        $oldValues = $bon->toArray();
        
        $bon->delete();

        // Log the deletion
        AuditService::log('delete', "Le bon provisoire \"{$numero}\" a été supprimé", 'BonProvisoireSg', $id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['bons-provisoires'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "Le bon provisoire \"{$numero}\" a été supprimé avec succès",
            'data' => null,
        ]);
    }

    public function articles(string $id, Request $request): JsonResponse
    {
        // Rechercher par ULID ou ID
        if (Str::isUlid($id)) {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        } elseif (is_numeric($id)) {
            $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        } else {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $page = $request->integer('page', 1);

        $cacheKey = sprintf('bons-provisoires.%s.articles.%s.%s', $bon->ulid ?? $bon->id, $page, $perPage);

        $payload = CacheTagger::tags(['bons-provisoires', 'bons-provisoires-articles'])->remember($cacheKey, 300, function () use ($bon, $perPage, $page) {
            $paginator = $bon->articles()->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()
                ->map(function ($article) use ($bon) {
                    $data = $article->toArray();
                    $data['bon_provisoire'] = [
                        'ulid' => $bon->ulid,
                        'identifiant' => $bon->identifiant,
                        'numero_bon_provisoire_complet' => $bon->numero_bon_provisoire_complet,
                    ];
                    return $data;
                })
                ->values()
                ->toArray();

            // Construire les liens de pagination
            $basePath = "/api/bons-provisoires/sg/{$bon->ulid}/articles";
            $queryParams = [];
            if ($perPage != 25) {
                $queryParams['per_page'] = $perPage;
            }

            $buildUrl = function ($pageNum) use ($basePath, $queryParams) {
                $params = array_merge($queryParams, ['page' => $pageNum]);
                return $basePath . '?' . http_build_query($params);
            };

            return [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'first' => $buildUrl(1),
                    'last' => $buildUrl($paginator->lastPage()),
                    'prev' => $paginator->currentPage() > 1 ? $buildUrl($paginator->currentPage() - 1) : null,
                    'next' => $paginator->currentPage() < $paginator->lastPage() ? $buildUrl($paginator->currentPage() + 1) : null,
                ],
                'items' => $items,
            ];
        });

        $meta = $payload['meta'];
        $links = $payload['links'];

        $message = $meta['total'] > 0
            ? sprintf(
                '%d article(s) trouvé(s) pour le bon provisoire "%s" (%s). Page %d/%d',
                $meta['total'],
                $bon->identifiant,
                $bon->numero_bon_provisoire_complet,
                $meta['current_page'],
                $meta['last_page']
            )
            : sprintf(
                'Aucun article enregistré pour le bon provisoire "%s" (%s). Créez votre premier article',
                $bon->identifiant,
                $bon->numero_bon_provisoire_complet
            );

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $payload['items'],
            'meta' => $meta,
            'links' => $links,
        ]);
    }

    public function validate(string $id, Request $request): JsonResponse
    {
        // Rechercher par ULID ou ID
        if (Str::isUlid($id)) {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        } elseif (is_numeric($id)) {
            $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        } else {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        }
        
        // Option pour dispatcher en queue (utile pour les validations complexes)
        $useQueue = $request->boolean('async', false);
        
        if ($useQueue) {
            $cacheKey = sprintf('bon-provisoire:validate:%s:%s', $bon->ulid ?? $bon->id, Str::random(12));
            
            Bus::dispatch(new ValidateBonProvisoire($bon->id, $cacheKey));
            
            return response()->json([
                'status' => 202,
                'message' => sprintf(
                    'Validation du bon provisoire "%s" (%s) en cours de traitement. Consultez le résultat via l\'URL fournie',
                    $bon->identifiant,
                    $bon->numero_bon_provisoire_complet
                ),
                'data' => [
                    'queued' => true,
                    'reference' => $cacheKey,
                    'check_url' => "/api/bons-provisoires/sg/{$bon->ulid}/validate/result?reference={$cacheKey}",
                ],
            ], 202);
        }
        
        // Exécution synchrone (par défaut)
        // Champs obligatoires pour la validation selon s360_analyse
        $requiredFields = [
            'numero_bon_provisoire' => 'Numéro du bon provisoire (ANNEE+BUREAU+SERIE_BP+NUM_SERIE_BP)',
            'num_lta' => 'Numéro LTA (Lettre de Transport Aérien)',
            'date_bp' => 'Date du bon provisoire',
            'nom_importateur' => 'Nom de l\'importateur',
        ];
        
        // Champs recommandés pour une validation complète
        $recommendedFields = [
            'date_expiration' => 'Date d\'expiration',
            'delai_jours' => 'Délai en jours',
            'nom_fournisseur' => 'Nom du fournisseur',
            'pays_origine' => 'Pays d\'origine',
            'ncc' => 'Numéro de Compte Client (NCC)',
            'code_declarant' => 'Code du déclarant',
            'nom_declarant' => 'Nom du déclarant',
            'type_bon_provisoire' => 'Type (IMP/EXP)',
        ];
        
        // Vérifier les champs obligatoires
        $missing = collect($requiredFields)
            ->filter(fn ($label, $field) => blank($bon->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
                'status' => 'required',
            ])
            ->values();

        // Vérifier les champs recommandés
        $missingRecommended = collect($recommendedFields)
            ->filter(fn ($label, $field) => blank($bon->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
                'status' => 'recommended',
            ])
            ->values();

        $isValid = $missing->isEmpty();
        $isComplete = $missingRecommended->isEmpty();
        
        // Compter les articles associés
        $articlesCount = $bon->articles()->count();
        
        // Vérifier la cohérence des dates
        $dateIssues = [];
        if ($bon->date_bp && $bon->date_expiration) {
            if ($bon->date_expiration->lt($bon->date_bp)) {
                $dateIssues[] = 'La date d\'expiration est antérieure à la date du bon provisoire';
            }
        }
        if ($bon->date_bp && $bon->date_lta) {
            if ($bon->date_lta->gt($bon->date_bp)) {
                $dateIssues[] = 'La date LTA est postérieure à la date du bon provisoire';
            }
        }
        
        // Vérifier la cohérence du numéro complet
        $numeroIssues = [];
        if ($bon->numero_bon_provisoire && $bon->annee && $bon->bureau && $bon->serie_bp && $bon->num_serie_bp) {
            $expectedNumero = sprintf('%s%s%s%s', $bon->annee, $bon->bureau, $bon->serie_bp, $bon->num_serie_bp);
            if ($bon->numero_bon_provisoire !== $expectedNumero) {
                $numeroIssues[] = sprintf(
                    'Incohérence du numéro : attendu "%s" (ANNEE+BUREAU+SERIE_BP+NUM_SERIE_BP), trouvé "%s"',
                    $expectedNumero,
                    $bon->numero_bon_provisoire
                );
            }
        }
        
        $allIssues = array_merge($dateIssues, $numeroIssues);
        $hasIssues = !empty($allIssues);
        
        $completionRate = 0;
        if (count($requiredFields) + count($recommendedFields) > 0) {
            $filled = (count($requiredFields) - $missing->count()) + (count($recommendedFields) - $missingRecommended->count());
            $total = count($requiredFields) + count($recommendedFields);
            $completionRate = round(($filled / $total) * 100, 2);
        }
        
        $message = $isValid && !$hasIssues
            ? ($isComplete 
                ? sprintf(
                    'Bon provisoire "%s" (%s) validé avec succès : tous les champs sont complets (%d%%)',
                    $bon->identifiant,
                    $bon->numero_bon_provisoire_complet,
                    $completionRate
                )
                : sprintf(
                    'Bon provisoire "%s" (%s) valide mais incomplet : %d%% de complétion. %d champ(s) recommandé(s) manquant(s)',
                    $bon->identifiant,
                    $bon->numero_bon_provisoire_complet,
                    $completionRate,
                    $missingRecommended->count()
                ))
            : sprintf(
                'Bon provisoire "%s" (%s) nécessite des corrections : %d champ(s) obligatoire(s) manquant(s)%s',
                $bon->identifiant,
                $bon->numero_bon_provisoire_complet,
                $missing->count(),
                $hasIssues ? ', ' . implode(', ', $allIssues) : ''
            );

        // Log the validation
        AuditService::log(
            'validate',
            sprintf(
                'Validation du bon provisoire "%s" (%s) - %s | %d article(s) associé(s)',
                $bon->identifiant,
                $bon->numero_bon_provisoire_complet,
                $isValid && !$hasIssues ? ($isComplete ? 'Valide et complet' : 'Valide mais incomplet') : 'Invalide',
                $articlesCount
            ),
            'BonProvisoireSg',
            $bon->id,
            null,
            [
                'valid' => $isValid && !$hasIssues,
                'complete' => $isComplete,
                'completion_rate' => $completionRate,
                'missing_fields' => $missing->toArray(),
                'missing_recommended_fields' => $missingRecommended->toArray(),
                'date_issues' => $dateIssues,
                'numero_issues' => $numeroIssues,
                'articles_count' => $articlesCount,
            ]
        );

        // Invalider le cache
        CacheTagger::tags(['bons-provisoires'])->flush();

        return response()->json([
            'status' => $isValid && !$hasIssues ? 200 : 422,
            'message' => $message,
            'data' => [
                'valid' => $isValid && !$hasIssues,
                'complete' => $isComplete,
                'completion_rate' => $completionRate,
                'missing_fields' => $missing->toArray(),
                'missing_recommended_fields' => $missingRecommended->toArray(),
                'issues' => $allIssues,
                'validation_summary' => [
                    'total_required_fields' => count($requiredFields),
                    'filled_required_fields' => count($requiredFields) - $missing->count(),
                    'total_recommended_fields' => count($recommendedFields),
                    'filled_recommended_fields' => count($recommendedFields) - $missingRecommended->count(),
                    'completion_rate' => $completionRate,
                ],
                'bon_provisoire_info' => [
                    'ulid' => $bon->ulid,
                    'id' => $bon->id,
                    'identifiant' => $bon->identifiant,
                    'numero_bon_provisoire_complet' => $bon->numero_bon_provisoire_complet,
                    'annee' => $bon->annee,
                    'bureau' => $bon->bureau,
                    'serie_bp' => $bon->serie_bp,
                    'num_serie_bp' => $bon->num_serie_bp,
                    'instance_id' => $bon->instance_id,
                    'date_bp' => $bon->date_bp?->format('Y-m-d'),
                    'date_expiration' => $bon->date_expiration?->format('Y-m-d'),
                    'date_lta' => $bon->date_lta?->format('Y-m-d'),
                    'delai_jours' => $bon->delai_jours,
                    'articles_count' => $articlesCount,
                ],
            ],
        ]);
    }
    
    public function validateResult(Request $request): JsonResponse
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return response()->json([
                'status' => 400,
                'message' => 'Le paramètre "reference" est requis',
                'data' => null,
            ], 400);
        }
        
        $result = CacheTagger::tags(['bons-provisoires'])->get($reference);
        
        if (!$result) {
            return response()->json([
                'status' => 202,
                'message' => 'La validation est encore en cours de traitement',
                'data' => [
                    'reference' => $reference,
                    'status' => 'processing',
                ],
            ], 202);
        }
        
        return response()->json([
            'status' => 200,
            'message' => 'Résultat de la validation récupéré avec succès',
            'data' => $result,
        ]);
    }

    public function expire(string $id, Request $request): JsonResponse
    {
        // Rechercher par ULID ou ID
        if (Str::isUlid($id)) {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        } elseif (is_numeric($id)) {
            $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        } else {
            $bon = BonProvisoireSg::where('ulid', $id)->firstOrFail();
        }
        
        $oldValues = $bon->toArray();
        $previousExpirationDate = $bon->date_expiration;
        
        // Vérifier si le bon était déjà expiré
        $wasAlreadyExpired = $bon->date_expiration && $bon->date_expiration->isPast();
        
        // Calculer le nombre de jours depuis la création
        $daysSinceCreation = $bon->date_bp ? now()->diffInDays($bon->date_bp) : null;
        $daysSinceExpiration = $bon->date_expiration ? now()->diffInDays($bon->date_expiration) : null;
        
        // Vérifier si la date d'expiration est fournie dans la requête
        $expirationDate = $request->input('date_expiration');
        if ($expirationDate) {
            try {
                $newExpirationDate = Carbon::parse($expirationDate);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Format de date invalide pour "date_expiration". Utilisez le format YYYY-MM-DD',
                    'data' => null,
                ], 422);
            }
        } else {
            // Par défaut, mettre à jour la date d'expiration à maintenant
            $newExpirationDate = now();
        }
        
        // Mettre à jour la date d'expiration
        $bon->update(['date_expiration' => $newExpirationDate]);
        $bon->refresh();
        
        // Compter les articles associés
        $articlesCount = $bon->articles()->count();
        
        // Vérifier si des articles sont liés à des déclarations
        $articlesWithDeclaration = $bon->articles()->whereNotNull('num_declaration')->count();
        
        // Log the expiration
        AuditService::log(
            'expire',
            sprintf(
                'Bon provisoire "%s" (%s) marqué comme expiré | Date précédente : %s | Nouvelle date : %s | %d article(s) associé(s)',
                $bon->identifiant,
                $bon->numero_bon_provisoire_complet,
                $previousExpirationDate ? $previousExpirationDate->format('d/m/Y') : 'N/A',
                $newExpirationDate->format('d/m/Y'),
                $articlesCount
            ),
            'BonProvisoireSg',
            $bon->id,
            $oldValues,
            $bon->toArray()
        );

        // Invalider le cache
        CacheTagger::tags(['bons-provisoires'])->flush();

        $message = $wasAlreadyExpired
            ? sprintf(
                'Bon provisoire "%s" (%s) était déjà expiré depuis %d jour(s). Date d\'expiration mise à jour au %s',
                $bon->identifiant,
                $bon->numero_bon_provisoire_complet,
                $daysSinceExpiration ?? 0,
                $newExpirationDate->format('d/m/Y')
            )
            : sprintf(
                'Bon provisoire "%s" (%s) marqué comme expiré le %s | Créé le %s (%d jour(s) de validité)',
                $bon->identifiant,
                $bon->numero_bon_provisoire_complet,
                $newExpirationDate->format('d/m/Y'),
                $bon->date_bp ? $bon->date_bp->format('d/m/Y') : 'N/A',
                $daysSinceCreation ?? 0
            );

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'expired_at' => $bon->date_expiration->format('Y-m-d H:i:s'),
                'expired_at_formatted' => $bon->date_expiration->format('d/m/Y'),
                'previous_expiration_date' => $previousExpirationDate ? $previousExpirationDate->format('Y-m-d') : null,
                'previous_expiration_date_formatted' => $previousExpirationDate ? $previousExpirationDate->format('d/m/Y') : null,
                'was_already_expired' => $wasAlreadyExpired,
                'days_since_creation' => $daysSinceCreation,
                'days_since_previous_expiration' => $daysSinceExpiration,
                'bon_provisoire_info' => [
                    'ulid' => $bon->ulid,
                    'id' => $bon->id,
                    'identifiant' => $bon->identifiant,
                    'numero_bon_provisoire_complet' => $bon->numero_bon_provisoire_complet,
                    'annee' => $bon->annee,
                    'bureau' => $bon->bureau,
                    'serie_bp' => $bon->serie_bp,
                    'num_serie_bp' => $bon->num_serie_bp,
                    'date_bp' => $bon->date_bp?->format('Y-m-d'),
                    'date_bp_formatted' => $bon->date_bp?->format('d/m/Y'),
                    'delai_jours' => $bon->delai_jours,
                    'articles_count' => $articlesCount,
                    'articles_with_declaration' => $articlesWithDeclaration,
                ],
            ],
        ]);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new BonProvisoireSg())->getFillable() as $attribute) {
            if ($attribute === 'id') {
                continue; // ID est généré automatiquement
            }
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['instance_id'] = $update ? 'sometimes|integer' : 'required|integer';
        
        // numero_bon_provisoire est requis SAUF si tous les composants sont fournis
        if ($update) {
            $rules['numero_bon_provisoire'] = 'sometimes|string|max:224';
        } else {
            // Pour la création, on utilise une validation conditionnelle
            // Si les composants sont fournis, numero_bon_provisoire devient optionnel
            $rules['numero_bon_provisoire'] = 'nullable|string|max:224';
        }

        return $rules;
    }

}










