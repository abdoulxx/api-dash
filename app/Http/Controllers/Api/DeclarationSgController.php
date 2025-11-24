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
                    $builder->where('declaration', 'like', "%{$search}%")
                        ->orWhere('num_manifeste', 'like', "%{$search}%")
                        ->orWhere('num_fdi', 'like', "%{$search}%")
                        ->orWhere('num_bl', 'like', "%{$search}%");
                })
                ->latest('date_declaration');

            $paginator = $query->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($search 
                    ? "{$paginator->total()} déclaration(s) correspondant à \"{$search}\" trouvée(s)"
                    : "{$paginator->total()} déclaration(s) récupérée(s) avec succès")
                : ($search
                    ? "Aucune déclaration ne correspond à votre recherche \"{$search}\""
                    : "Aucune déclaration enregistrée pour le moment");

            return [
                'status' => 200,
                'message' => $message,
                'data' => $paginator->items(),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $declaration = DeclarationSg::create($validated);

        // Log the creation
        $numero = $declaration->declaration ?? "N°{$declaration->id}";
        AuditService::log('create', "La déclaration \"{$numero}\" a été créée", 'DeclarationSg', $declaration->id, null, $declaration->toArray());

        // Invalider le cache
        CacheTagger::tags(['declarations'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "La déclaration \"{$numero}\" a été créée avec succès",
            'data' => $declaration->fresh()->toArray(),
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $cacheKey = "declarations:show:{$ulid}";

        $payload = CacheTagger::tags(['declarations'])->remember($cacheKey, 300, function () use ($ulid) {
            $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
            $declaration->load(['manifeste', 'fdi', 'fcvr', 'articles']);
            
            return [
                'status' => 200,
                'message' => "Détails de la déclaration \"{$declaration->declaration}\" récupérés avec succès",
                'data' => $declaration->toArray(),
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
        $numero = $declaration->declaration ?? "N°{$declaration->id}";
        AuditService::log('update', "La déclaration \"{$numero}\" a été mise à jour", 'DeclarationSg', $declaration->id, $oldValues, $declaration->toArray());

        // Invalider le cache
        CacheTagger::tags(['declarations'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La déclaration \"{$numero}\" a été mise à jour avec succès",
            'data' => $declaration->toArray(),
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        $numero = $declaration->declaration ?? "N°{$declaration->id}";
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
        
        $message = $manifeste
            ? "Manifeste lié à la déclaration \"{$declaration->declaration}\" récupéré avec succès"
            : "Aucun manifeste lié à la déclaration \"{$declaration->declaration}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $manifeste ? $manifeste->toArray() : null,
        ]);
    }

    public function articles(string $ulid, Request $request): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        $perPage = min($request->integer('per_page', 25), 100);
        $paginator = $declaration->articles()->paginate($perPage);

        $message = $paginator->total() > 0
            ? "{$paginator->total()} article(s) trouvé(s) pour la déclaration \"{$declaration->declaration}\""
            : "Aucun article trouvé pour la déclaration \"{$declaration->declaration}\"";

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

    public function conteneurs(string $ulid, Request $request): JsonResponse
    {
        $declaration = DeclarationSg::where('ulid', $ulid)->firstOrFail();
        $perPage = min($request->integer('per_page', 25), 100);
        $query = DeclarationTc::query()
            ->where('num_manifeste', $declaration->num_manifeste)
            ->orWhere('instanceid', $declaration->instanceid);

        $paginator = $query->paginate($perPage);

        $message = $paginator->total() > 0
            ? "{$paginator->total()} conteneur(s) trouvé(s) pour la déclaration \"{$declaration->declaration}\""
            : "Aucun conteneur trouvé pour la déclaration \"{$declaration->declaration}\"";

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
        
        $message = $isValid
            ? ($isComplete 
                ? "La déclaration \"{$declaration->declaration}\" est valide et complète"
                : "La déclaration \"{$declaration->declaration}\" est valide mais des informations recommandées sont manquantes")
            : "La déclaration est invalide : " . ($missing->count() === 1 ? "le champ \"{$missing->first()['label']}\" est manquant" : "plusieurs champs obligatoires sont manquants");

        // Log the validation
        AuditService::log('validate', "Validation de la déclaration \"{$declaration->declaration}\" - " . ($isValid ? ($isComplete ? 'Valide et complète' : 'Valide mais incomplète') : 'Invalide'), 'DeclarationSg', $declaration->id, null, [
            'valid' => $isValid,
            'complete' => $isComplete,
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
                'missing_fields' => $missing->toArray(),
                'missing_recommended_fields' => $missingRecommended->toArray(),
                'validation_summary' => [
                    'total_required_fields' => count($requiredFields),
                    'filled_required_fields' => count($requiredFields) - $missing->count(),
                    'total_recommended_fields' => count($recommendedFields),
                    'filled_recommended_fields' => count($recommendedFields) - $missingRecommended->count(),
                ],
                'declaration_info' => [
                    'id' => $declaration->id,
                    'ulid' => $declaration->ulid,
                    'declaration' => $declaration->declaration,
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
            
            return response()->json([
                'status' => 202,
                'message' => "Calcul des taxes en cours de traitement pour la déclaration \"{$declaration->declaration}\"",
                'data' => [
                    'queued' => true,
                    'reference' => $cacheKey,
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

        // Log the tax calculation
        AuditService::log('calculate-taxes', "Calcul des taxes effectué pour la déclaration \"{$declaration->declaration}\" ({$articlesCount} article(s))", 'DeclarationSg', $declaration->id, null, [
            'articles_count' => $articlesCount,
            'totals' => [
                'caf' => (float) ($totals->caf ?? 0),
                'fob' => (float) ($totals->fob ?? 0),
                'taxes' => (float) ($totals->taxes ?? 0),
            ],
        ]);

        return response()->json([
            'status' => 200,
            'message' => "Calcul des taxes effectué pour la déclaration \"{$declaration->declaration}\" ({$articlesCount} article(s))",
            'data' => [
                'declaration' => $declaration->declaration,
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


