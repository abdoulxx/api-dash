<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ValidateBonProvisoire;
use App\Models\BonProvisoireArticle;
use App\Models\BonProvisoireSg;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonProvisoireSgController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'bons-provisoires:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['bons-provisoires'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $search = $request->string('search')->toString();

            $paginator = BonProvisoireSg::query()
                ->when($search, fn ($query) => $query->where('numero_bon_provisoire', 'like', "%{$search}%")
                    ->orWhere('num_lta', 'like', "%{$search}%")
                    ->orWhere('nom_importateur', 'like', "%{$search}%"))
                ->latest('date_bp')
                ->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($search 
                    ? "{$paginator->total()} bon(s) provisoire(s) correspondant à \"{$search}\" trouvé(s)"
                    : "{$paginator->total()} bon(s) provisoire(s) récupéré(s) avec succès")
                : ($search
                    ? "Aucun bon provisoire ne correspond à votre recherche \"{$search}\""
                    : "Aucun bon provisoire enregistré pour le moment");

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

        $bon = BonProvisoireSg::create($validated);

        // Log the creation
        AuditService::log('create', "Le bon provisoire \"{$bon->numero_bon_provisoire}\" a été créé", 'BonProvisoireSg', $bon->id, null, $bon->toArray());

        // Invalider le cache
        CacheTagger::tags(['bons-provisoires'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "Le bon provisoire \"{$bon->numero_bon_provisoire}\" a été créé avec succès",
            'data' => $bon->fresh()->toArray(),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $cacheKey = "bons-provisoires:show:{$id}";

        $payload = CacheTagger::tags(['bons-provisoires'])->remember($cacheKey, 300, function () use ($id) {
            // Rechercher le bon provisoire par ULID
            $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
            
            // Charger les articles
            $bon->load('articles');
            
            // S'assurer que toutes les données sont présentes
            $numero = $bon->numero_bon_provisoire ?? 'N/A';
            
            // Construire la réponse avec toutes les données
            $data = [
                'id' => $bon->id,
                'instance_id' => $bon->instance_id,
                'annee' => $bon->annee,
                'bureau' => $bon->bureau,
                'serie_bp' => $bon->serie_bp,
                'num_serie_bp' => $bon->num_serie_bp,
                'numero_bon_provisoire' => $bon->numero_bon_provisoire,
                'date_bp' => $bon->date_bp,
                'num_vol_lta' => $bon->num_vol_lta,
                'num_lta' => $bon->num_lta,
                'date_lta' => $bon->date_lta,
                'date_expiration' => $bon->date_expiration,
                'delai_jours' => $bon->delai_jours,
                'ncc' => $bon->ncc,
                'nom_importateur' => $bon->nom_importateur,
                'nom_fournisseur' => $bon->nom_fournisseur,
                'pays_origine' => $bon->pays_origine,
                'code_declarant' => $bon->code_declarant,
                'nom_declarant' => $bon->nom_declarant,
                'type_bon_provisoire' => $bon->type_bon_provisoire,
                'created_at' => $bon->created_at,
                'updated_at' => $bon->updated_at,
                'articles' => $bon->articles->map(function ($article) {
                    return $article->toArray();
                })->toArray(),
            ];
            
            return [
                'status' => 200,
                'message' => "Détails du bon provisoire \"{$numero}\" récupérés avec succès",
                'data' => $data,
            ];
        });

        return response()->json($payload);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        
        $validated = $request->validate($this->rules(true));
        $oldValues = $bon->toArray();

        $bon->fill($validated);
        $bon->save();

        // Log the update
        AuditService::log('update', "Le bon provisoire \"{$bon->numero_bon_provisoire}\" a été modifié", 'BonProvisoireSg', $bon->id, $oldValues, $bon->toArray());

        // Invalider le cache
        CacheTagger::tags(['bons-provisoires'])->flush();

        $updatedFields = array_keys(array_diff_assoc($validated, $oldValues));
        $fieldsCount = count($updatedFields);

        return response()->json([
            'status' => 200,
            'message' => "Le bon provisoire \"{$bon->numero_bon_provisoire}\" a été modifié avec succès" . ($fieldsCount > 0 ? " ({$fieldsCount} champ(s) mis à jour)" : ""),
            'data' => $bon->fresh()->toArray(),
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
        $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        $perPage = min($request->integer('per_page', 25), 100);

        $paginator = $bon->articles()->paginate($perPage);

        $message = $paginator->total() > 0
            ? "{$paginator->total()} article(s) trouvé(s) pour le bon provisoire \"{$bon->numero_bon_provisoire}\""
            : "Aucun article trouvé pour le bon provisoire \"{$bon->numero_bon_provisoire}\"";

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

    public function validate(string $id, Request $request): JsonResponse
    {
        $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        
        // Option pour dispatcher en queue (utile pour les validations complexes)
        $useQueue = $request->boolean('async', false);
        
        if ($useQueue) {
            $cacheKey = sprintf('bon-provisoire:validate:%s:%s', $id, Str::random(12));
            
            Bus::dispatch(new ValidateBonProvisoire($id, $cacheKey));
            
            return response()->json([
                'status' => 202,
                'message' => "Validation du bon provisoire \"{$bon->numero_bon_provisoire}\" en cours de traitement",
                'data' => [
                    'queued' => true,
                    'reference' => $cacheKey,
                    'check_url' => "/api/bons-provisoires/sg/{$id}/validate/result?reference={$cacheKey}",
                ],
            ], 202);
        }
        
        // Exécution synchrone (par défaut)
        // Champs obligatoires pour la validation
        $requiredFields = [
            'numero_bon_provisoire' => 'Numéro du bon provisoire',
            'num_lta' => 'Numéro LTA',
        ];
        
        // Champs recommandés
        $recommendedFields = [
            'date_bp' => 'Date du bon provisoire',
            'date_expiration' => 'Date d\'expiration',
            'nom_importateur' => 'Nom de l\'importateur',
            'nom_fournisseur' => 'Nom du fournisseur',
            'ncc' => 'NCC',
        ];
        
        // Vérifier les champs obligatoires
        $missing = collect($requiredFields)
            ->filter(fn ($label, $field) => blank($bon->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        // Vérifier les champs recommandés
        $missingRecommended = collect($recommendedFields)
            ->filter(fn ($label, $field) => blank($bon->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        $isValid = $missing->isEmpty();
        $isComplete = $missingRecommended->isEmpty();
        
        // Compter les articles associés
        $articlesCount = $bon->articles()->count();
        
        $message = $isValid
            ? ($isComplete 
                ? "Le bon provisoire \"{$bon->numero_bon_provisoire}\" est valide et complet"
                : "Le bon provisoire \"{$bon->numero_bon_provisoire}\" est valide mais incomplet")
            : "Le bon provisoire est invalide : " . ($missing->count() === 1 ? "le champ \"{$missing->first()['label']}\" est manquant" : "plusieurs champs obligatoires sont manquants");

        // Log the validation
        AuditService::log('validate', "Validation du bon provisoire \"{$bon->numero_bon_provisoire}\" - " . ($isValid ? ($isComplete ? 'Valide et complet' : 'Valide mais incomplet') : 'Invalide'), 'BonProvisoireSg', $bon->id, null, [
            'valid' => $isValid,
            'complete' => $isComplete,
            'missing_fields' => $missing->toArray(),
            'articles_count' => $articlesCount,
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
                'bon_provisoire_info' => [
                    'id' => $bon->id,
                    'numero_bon_provisoire' => $bon->numero_bon_provisoire,
                    'instance_id' => $bon->instance_id,
                    'date_bp' => $bon->date_bp,
                    'date_expiration' => $bon->date_expiration,
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

    public function expire(string $id): JsonResponse
    {
        $bon = BonProvisoireSg::where('id', $id)->firstOrFail();
        
        // Vérifier si le bon était déjà expiré
        $wasAlreadyExpired = $bon->date_expiration && $bon->date_expiration->isPast();
        $previousExpirationDate = $bon->date_expiration;
        $oldValues = $bon->toArray();
        
        // Mettre à jour la date d'expiration à maintenant
        $bon->update(['date_expiration' => now()]);
        $bon->refresh();

        // Log the expiration
        AuditService::log('expire', "Le bon provisoire \"{$bon->numero_bon_provisoire}\" a été marqué comme expiré" . ($wasAlreadyExpired ? " (était déjà expiré)" : ""), 'BonProvisoireSg', $bon->id, $oldValues, $bon->toArray());

        $message = $wasAlreadyExpired
            ? "Le bon provisoire \"{$bon->numero_bon_provisoire}\" était déjà expiré. La date d'expiration a été mise à jour"
            : "Le bon provisoire \"{$bon->numero_bon_provisoire}\" a été marqué comme expiré";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'expired_at' => $bon->date_expiration,
                'previous_expiration_date' => $previousExpirationDate,
                'was_already_expired' => $wasAlreadyExpired,
                'bon_provisoire_info' => [
                    'id' => $bon->id,
                    'numero_bon_provisoire' => $bon->numero_bon_provisoire,
                    'date_bp' => $bon->date_bp,
                    'delai_jours' => $bon->delai_jours,
                ],
            ],
        ]);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new BonProvisoireSg())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['instance_id'] = $update ? 'sometimes|integer' : 'required|integer';
        $rules['numero_bon_provisoire'] = $update ? 'sometimes|string|max:120' : 'required|string|max:120';

        return $rules;
    }

}




