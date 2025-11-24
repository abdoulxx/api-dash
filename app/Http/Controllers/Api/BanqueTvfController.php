<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BanqueTvf;
use App\Models\FdiSg;
use App\Services\AuditService;
use App\Services\BanqueService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BanqueTvfController extends Controller
{
    public function __construct(private readonly BanqueService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'banque-tvf:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, 300, function () use ($request) {
        $perPage = min($request->integer('per_page', 15), 100);
        $search = $request->string('search')->toString();

        $paginator = BanqueTvf::query()
                ->when($search, fn ($query) => $query->where('num_fdi', 'like', "%{$search}%")
                    ->orWhere('ref_ddu', 'like', "%{$search}%")
                    ->orWhere('num_dom', 'like', "%{$search}%"))
            ->latest('date_fdi')
            ->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($search 
                    ? "Recherche \"{$search}\" : {$paginator->total()} TVF trouvée(s). " .
                      "Page {$paginator->currentPage()}/{$paginator->lastPage()}"
                    : "{$paginator->total()} TVF récupérée(s). " .
                      "Page {$paginator->currentPage()}/{$paginator->lastPage()}")
                : ($search
                    ? "Aucun résultat pour \"{$search}\". Essayez un numéro FDI, DDU ou DOM"
                    : "Aucun enregistrement TVF pour le moment");

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

        $banqueTvf = BanqueTvf::create($validated);

        // Log the creation
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        
        $details = [];
        if ($banqueTvf->pays_exp) $details[] = "Pays : {$banqueTvf->pays_exp}";
        if ($banqueTvf->statut_ac) $details[] = "Statut : {$banqueTvf->statut_ac}";
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
        
        AuditService::log('create', "Nouvelle TVF créée : {$numero}{$detailsStr}", 'BanqueTvf', $banqueTvf->id, null, $banqueTvf->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "TVF \"{$numero}\" enregistrée{$detailsStr}. ULID : {$banqueTvf->ulid}",
            'data' => $banqueTvf->fresh()->toArray(),
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $cacheKey = "banque-tvf:show:{$banqueTvf->ulid}";

        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, 300, function () use ($banqueTvf) {
            // Charger les comparaisons
            $banqueTvf->load(['comparaison1', 'comparaison2']);
            
            // La FDI est accessible via l'accessor $banqueTvf->fdi
            $fdi = $banqueTvf->fdi;
            
            // Construire un numéro d'identification plus informatif
            $identifiants = [];
            if ($banqueTvf->num_fdi) {
                $identifiants[] = "FDI {$banqueTvf->num_fdi}";
            }
            if ($banqueTvf->ref_ddu) {
                $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
            }
            if ($banqueTvf->num_dom) {
                $identifiants[] = "DOM {$banqueTvf->num_dom}";
            }
            $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
            
            // Construire un message informatif
            $details = [];
            if ($banqueTvf->statut_ac) $details[] = "Statut : {$banqueTvf->statut_ac}";
            if ($banqueTvf->date_fdi) $details[] = "Date FDI : " . $banqueTvf->date_fdi->format('d/m/Y');
            if ($banqueTvf->pays_exp) $details[] = "Pays : {$banqueTvf->pays_exp}";
            if ($banqueTvf->bank_dom) $details[] = "Banque : {$banqueTvf->bank_dom}";
            $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
            
            $message = "Détails de la TVF \"{$numero}\" récupérés{$detailsStr}";
            if ($fdi) {
                $message .= " | FDI liée : {$fdi->numero_fdi}";
            }

            // Retourner toutes les données de l'enregistrement TVF
            // Utiliser toArray() qui applique les casts automatiquement
            $data = $banqueTvf->toArray();
            
            // Retirer les relations si elles sont présentes dans les attributs (elles seront ajoutées séparément)
            unset($data['fdi'], $data['comparaison1'], $data['comparaison2']);
            
            // Ajouter les relations séparément pour plus de clarté
            $data['relations'] = [
                'fdi' => $fdi ? $fdi->toArray() : null,
                'comparaison1' => $banqueTvf->comparaison1 ? $banqueTvf->comparaison1->toArray() : null,
                'comparaison2' => $banqueTvf->comparaison2 ? $banqueTvf->comparaison2->toArray() : null,
            ];
            
            // S'assurer que deleted_at n'est pas retourné dans les données principales
            if (isset($data['deleted_at']) && $data['deleted_at'] === null) {
                unset($data['deleted_at']);
            }

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
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $validated = $request->validate($this->rules(true));
        $oldValues = $banqueTvf->toArray();

        $banqueTvf->fill($validated);
        $banqueTvf->save();

        // Log the update
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        
        $champsModifies = array_keys(array_diff_assoc($banqueTvf->toArray(), $oldValues));
        $nbModifs = count($champsModifies);
        $modifsStr = $nbModifs > 0 ? " ({$nbModifs} champ(s) modifié(s) : " . implode(', ', array_slice($champsModifies, 0, 5)) . ($nbModifs > 5 ? '...' : '') . ")" : "";
        
        AuditService::log('update', "TVF \"{$numero}\" mise à jour{$modifsStr}", 'BanqueTvf', $banqueTvf->id, $oldValues, $banqueTvf->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "TVF \"{$numero}\" mise à jour{$modifsStr}. Dernière modification : " . now()->format('d/m/Y H:i'),
            'data' => $banqueTvf->fresh()->toArray(),
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        $oldValues = $banqueTvf->toArray();
        
        $banqueTvf->delete();

        // Log the deletion
        $statutInfo = $banqueTvf->statut_ac ? " (Statut : {$banqueTvf->statut_ac})" : '';
        $dateInfo = $banqueTvf->date_fdi ? " | Date FDI : " . $banqueTvf->date_fdi->format('d/m/Y') : '';
        AuditService::log('delete', "TVF \"{$numero}\"{$statutInfo}{$dateInfo} supprimée (soft delete). Restauration possible via /restore", 'BanqueTvf', $banqueTvf->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "TVF \"{$numero}\" supprimée (soft delete). POST /api/banque/tvf/{$banqueTvf->ulid}/restore permet de la rétablir.",
            'data' => null,
        ]);
    }
    
    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'banque-tvf:trashed:' . md5($request->fullUrl());
        
        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $search = $request->string('search')->toString();
            
            $query = BanqueTvf::onlyTrashed();
            
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_fdi', 'like', "%{$search}%")
                      ->orWhere('ref_ddu', 'like', "%{$search}%")
                      ->orWhere('num_dom', 'like', "%{$search}%");
                });
            }
            
            $banques = $query->latest('deleted_at')->paginate($perPage);
            
            $message = $banques->total() > 0
                ? "{$banques->total()} TVF supprimée(s) recensée(s). Page {$banques->currentPage()}/{$banques->lastPage()}"
                : "Aucune TVF en corbeille.";
            
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
    
    public function restore(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        $oldValues = $banqueTvf->toArray();
        
        $banqueTvf->restore();
        
        // Log the restoration
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        
        $dateSuppression = $banqueTvf->deleted_at ? " (supprimée le " . $banqueTvf->deleted_at->format('d/m/Y H:i') . ")" : "";
        AuditService::log('restore', "TVF \"{$numero}\" restaurée depuis la corbeille{$dateSuppression}", 'BanqueTvf', $banqueTvf->id, $oldValues, $banqueTvf->toArray());
        
        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();
        
        return response()->json([
            'status' => 200,
            'message' => "TVF \"{$numero}\" restaurée. L'enregistrement redevient actif.",
            'data' => $banqueTvf->fresh()->toArray(),
        ]);
    }
    
    public function forceDelete(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        $oldValues = $banqueTvf->toArray();
        
        // Log the permanent deletion before deleting
        AuditService::log('force_delete', "TVF \"{$numero}\" supprimée définitivement (suppression permanente)", 'BanqueTvf', $banqueTvf->id, $oldValues, []);
        
        $banqueTvf->forceDelete();
        
        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();
        
        return response()->json([
            'status' => 200,
            'message' => "TVF \"{$numero}\" supprimée définitivement. Action irréversible.",
            'data' => null,
        ]);
    }

    public function fdi(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        
        // Récupérer la FDI manuellement car la relation nécessite une conversion de type
        $fdi = null;
        if ($banqueTvf->num_fdi) {
            $numFdiStr = (string) (int) $banqueTvf->num_fdi;
            $fdi = FdiSg::where('numero_fdi', $numFdiStr)->first();
        }
        
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        
        $message = $fdi
            ? "FDI \"{$fdi->numero_fdi}\" liée à la TVF \"{$numero}\"."
            : "Aucune FDI trouvée pour la TVF \"{$numero}\". Vérifiez le numéro FDI ({$banqueTvf->num_fdi}).";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $fdi ? $fdi->toArray() : null,
        ]);
    }

    public function comparaisons(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        
        $comp1 = $banqueTvf->comparaison1;
        $comp2 = $banqueTvf->comparaison2;
        $total = ($comp1 ? 1 : 0) + ($comp2 ? 1 : 0);

        $message = $total > 0
            ? "{$total} comparaison(s) disponible(s) pour la TVF \"{$numero}\"."
            : "Aucune comparaison enregistrée pour la TVF \"{$numero}\".";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'comparaison_1' => $comp1 ? $comp1->toArray() : null,
                'comparaison_2' => $comp2 ? $comp2->toArray() : null,
            ],
        ]);
    }

    public function validate(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        
        $domiciliationOk = $this->service->verifyDomiciliation($banqueTvf);
        
        $details = [];
        if ($banqueTvf->num_dom) $details[] = "N° DOM : {$banqueTvf->num_dom}";
        if ($banqueTvf->date_dom) $details[] = "Date : " . $banqueTvf->date_dom->format('d/m/Y');
        if ($banqueTvf->bank_dom) $details[] = "Banque : {$banqueTvf->bank_dom}";
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : "";

        // Log the validation
        $validationDetails = $domiciliationOk 
            ? "Domiciliation complète{$detailsStr}" 
            : "Domiciliation incomplète - éléments manquants";
        
        AuditService::log('validate', "Validation TVF \"{$numero}\" : {$validationDetails}", 'BanqueTvf', $banqueTvf->id, null, [
            'domiciliation_ok' => $domiciliationOk,
            'num_dom' => $banqueTvf->num_dom,
            'date_dom' => $banqueTvf->date_dom,
        ]);

        return response()->json([
            'status' => 200,
            'message' => $domiciliationOk 
                ? "TVF \"{$numero}\" validée : domiciliation complète{$detailsStr}. Prête pour traitement."
                : "TVF \"{$numero}\" : domiciliation incomplète. Vérifiez le numéro DOM et la date{$detailsStr}.",
            'data' => [
                'domiciliation_ok' => $domiciliationOk,
                'banque_tvf_info' => [
                    'id' => $banqueTvf->id,
                    'ulid' => $banqueTvf->ulid,
                    'num_fdi' => $banqueTvf->num_fdi,
                    'ref_ddu' => $banqueTvf->ref_ddu,
                    'num_dom' => $banqueTvf->num_dom,
                    'date_dom' => $banqueTvf->date_dom,
                    'bank_dom' => $banqueTvf->bank_dom,
                ],
            ],
        ]);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new BanqueTvf())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['num_fdi'] = $update ? 'sometimes' : 'required';

        return $rules;
    }

}




