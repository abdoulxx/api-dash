<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BanqueSad;
use App\Services\AuditService;
use App\Services\BanqueService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BanqueSadController extends Controller
{
    public function __construct(private readonly BanqueService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'banque-sad:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque-sad'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $search = $request->string('search')->toString();

            $paginator = BanqueSad::query()
                ->when($search, function ($query) use ($search) {
                    $query->where('num_ddu', 'like', "%{$search}%")
                        ->orWhere('num_man', 'like', "%{$search}%")
                        ->orWhere('num_dom', 'like', "%{$search}%")
                        ->orWhere('ref_ddu', 'like', "%{$search}%");
                })
                ->latest('date_ddu')
                ->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($search 
                    ? "Recherche \"{$search}\" : {$paginator->total()} SAD trouvée(s). Page {$paginator->currentPage()}/{$paginator->lastPage()}"
                    : "Liste des SAD : {$paginator->total()} enregistrement(s). Page {$paginator->currentPage()}/{$paginator->lastPage()}")
                : ($search
                    ? "Aucun résultat pour \"{$search}\". Essayez avec un numéro DDU, manifeste ou DOM"
                    : "Aucune SAD enregistrée. Créez votre premier enregistrement SAD");

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

        $banqueSad = BanqueSad::create($validated);

        // Log the creation
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        
        $details = [];
        if ($banqueSad->statut_ac) $details[] = "Statut : {$banqueSad->statut_ac}";
        if ($banqueSad->pays_exp) $details[] = "Pays : {$banqueSad->pays_exp}";
        if ($banqueSad->bank_dom) $details[] = "Banque : {$banqueSad->bank_dom}";
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
        
        AuditService::log('create', "Nouvelle SAD créée : {$numero}{$detailsStr}", 'BanqueSad', $banqueSad->id, null, $banqueSad->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "SAD \"{$numero}\" créée avec succès{$detailsStr}. ULID : {$banqueSad->ulid}",
            'data' => $banqueSad->fresh()->toArray(),
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $cacheKey = "banque-sad:show:{$banqueSad->ulid}";

        $payload = CacheTagger::tags(['banque-sad'])->remember($cacheKey, 300, function () use ($banqueSad) {
            $banqueSad->load(['declaration', 'manifeste']);
            
            $identifiants = [];
            if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
            if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
            $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
            
            $details = [];
            if ($banqueSad->statut_ac) $details[] = "Statut : {$banqueSad->statut_ac}";
            if ($banqueSad->date_ddu) $details[] = "Date DDU : " . $banqueSad->date_ddu->format('d/m/Y');
            if ($banqueSad->pays_exp) $details[] = "Pays : {$banqueSad->pays_exp}";
            if ($banqueSad->bank_dom) $details[] = "Banque : {$banqueSad->bank_dom}";
            $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
            
            $message = "Détails de la SAD \"{$numero}\" récupérés{$detailsStr}";
            if ($banqueSad->declaration) {
                $message .= " | Déclaration liée : {$banqueSad->declaration->declaration}";
            }
            if ($banqueSad->manifeste) {
                $message .= " | Manifeste lié : {$banqueSad->manifeste->num_manifeste}";
            }

            $data = $banqueSad->toArray();
            unset($data['declaration'], $data['manifeste']);
            
            $data['relations'] = [
                'declaration' => $banqueSad->declaration ? $banqueSad->declaration->toArray() : null,
                'manifeste' => $banqueSad->manifeste ? $banqueSad->manifeste->toArray() : null,
            ];

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
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $validated = $request->validate($this->rules(true));
        $oldValues = $banqueSad->toArray();

        $banqueSad->fill($validated);
        $banqueSad->save();

        // Log the update
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        
        $champsModifies = array_keys(array_diff_assoc($banqueSad->toArray(), $oldValues));
        $nbModifs = count($champsModifies);
        $modifsStr = $nbModifs > 0 ? " ({$nbModifs} champ(s) modifié(s) : " . implode(', ', array_slice($champsModifies, 0, 5)) . ($nbModifs > 5 ? '...' : '') . ")" : "";
        
        AuditService::log('update', "SAD \"{$numero}\" mise à jour{$modifsStr}", 'BanqueSad', $banqueSad->id, $oldValues, $banqueSad->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "SAD \"{$numero}\" modifiée avec succès{$modifsStr}. Dernière mise à jour : " . now()->format('d/m/Y H:i'),
            'data' => $banqueSad->fresh()->toArray(),
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        $oldValues = $banqueSad->toArray();
        
        $banqueSad->delete();

        // Log the deletion
        $statutInfo = $banqueSad->statut_ac ? " (Statut : {$banqueSad->statut_ac})" : '';
        $dateInfo = $banqueSad->date_ddu ? " | Date DDU : " . $banqueSad->date_ddu->format('d/m/Y') : '';
        AuditService::log('delete', "SAD \"{$numero}\"{$statutInfo}{$dateInfo} supprimée (soft delete). Restauration possible via /restore", 'BanqueSad', $banqueSad->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "SAD \"{$numero}\" supprimée. POST /api/banque/sad/{$banqueSad->ulid}/restore permet de la rétablir.",
            'data' => null,
        ]);
    }
    
    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'banque-sad:trashed:' . md5($request->fullUrl());
        
        $payload = CacheTagger::tags(['banque-sad'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $search = $request->string('search')->toString();
            
            $query = BanqueSad::onlyTrashed();
            
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_ddu', 'like', "%{$search}%")
                      ->orWhere('num_man', 'like', "%{$search}%")
                      ->orWhere('num_dom', 'like', "%{$search}%")
                      ->orWhere('ref_ddu', 'like', "%{$search}%");
                });
            }
            
            $sads = $query->latest('deleted_at')->paginate($perPage);
            
            $message = $sads->total() > 0
                ? "{$sads->total()} SAD supprimée(s) recensée(s). Page {$sads->currentPage()}/{$sads->lastPage()}"
                : "Aucune SAD en corbeille.";
            
            return [
                'status' => 200,
                'message' => $message,
                'data' => $sads->items(),
                'meta' => [
                    'current_page' => $sads->currentPage(),
                    'last_page' => $sads->lastPage(),
                    'per_page' => $sads->perPage(),
                    'total' => $sads->total(),
                ],
            ];
        });
        
        return response()->json($payload);
    }
    
    public function restore(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        $oldValues = $banqueSad->toArray();
        
        $banqueSad->restore();
        
        // Log the restoration
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        
        $dateSuppression = $banqueSad->deleted_at ? " (supprimée le " . $banqueSad->deleted_at->format('d/m/Y H:i') . ")" : "";
        AuditService::log('restore', "SAD \"{$numero}\" restaurée depuis la corbeille{$dateSuppression}", 'BanqueSad', $banqueSad->id, $oldValues, $banqueSad->toArray());
        
        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();
        
        return response()->json([
            'status' => 200,
            'message' => "SAD \"{$numero}\" restaurée. L'enregistrement redevient actif.",
            'data' => $banqueSad->fresh()->toArray(),
        ]);
    }
    
    public function forceDelete(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        $oldValues = $banqueSad->toArray();
        
        // Log the permanent deletion before deleting
        AuditService::log('force_delete', "SAD \"{$numero}\" supprimée définitivement (suppression permanente)", 'BanqueSad', $banqueSad->id, $oldValues, []);
        
        $banqueSad->forceDelete();
        
        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();
        
        return response()->json([
            'status' => 200,
            'message' => "SAD \"{$numero}\" supprimée définitivement. Cette action est irréversible.",
            'data' => null,
        ]);
    }

    public function declaration(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $declaration = $banqueSad->declaration;
        
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        
        $message = $declaration
            ? "Déclaration \"{$declaration->declaration}\" liée à la SAD \"{$numero}\". " .
              ($declaration->date_declaration ? "Date : " . $declaration->date_declaration->format('d/m/Y') : "") .
              ($declaration->importateur ? " | Importateur : {$declaration->importateur}" : "")
            : "Aucune déclaration liée à la SAD \"{$numero}\". Vérifiez le ref_ddu ({$banqueSad->ref_ddu}) dans la table declaration_sg";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $declaration ? $declaration->toArray() : null,
        ]);
    }

    public function manifeste(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $manifeste = $banqueSad->manifeste;
        
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        
        $message = $manifeste
            ? "Manifeste \"{$manifeste->num_manifeste}\" lié à la SAD \"{$numero}\". " .
              ($manifeste->date_manifeste ? "Date : " . $manifeste->date_manifeste->format('d/m/Y') : "") .
              ($manifeste->bureau ? " | Bureau : {$manifeste->bureau}" : "")
            : "Aucun manifeste lié à la SAD \"{$numero}\". Vérifiez le num_man ({$banqueSad->num_man}) dans la table manifeste_sg";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $manifeste ? $manifeste->toArray() : null,
        ]);
    }

    public function validate(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $identifiants = [];
        if ($banqueSad->num_ddu) $identifiants[] = "DDU {$banqueSad->num_ddu}";
        if ($banqueSad->num_man) $identifiants[] = "Manifeste {$banqueSad->num_man}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        
        $result = $this->service->verifyPayment($banqueSad);
        $paymentOk = $result['payment_verified'] ?? false;
        
        $details = [];
        if ($banqueSad->num_dom) $details[] = "N° DOM : {$banqueSad->num_dom}";
        if ($banqueSad->date_dom) $details[] = "Date : " . $banqueSad->date_dom->format('d/m/Y');
        if ($banqueSad->bank_dom) $details[] = "Banque : {$banqueSad->bank_dom}";
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : "";

        // Log the validation
        $validationDetails = $paymentOk 
            ? "Paiement vérifié{$detailsStr}" 
            : "Paiement non vérifié - Vérifiez les montants et la domiciliation";
        
        AuditService::log('validate', "Validation SAD \"{$numero}\" : {$validationDetails}", 'BanqueSad', $banqueSad->id, null, [
            'payment_verified' => $paymentOk,
            'num_dom' => $banqueSad->num_dom,
            'date_dom' => $banqueSad->date_dom?->format('Y-m-d'),
        ]);

        return response()->json([
            'status' => 200,
            'message' => $paymentOk 
                ? "SAD \"{$numero}\" validée : paiement vérifié{$detailsStr}. Prête pour traitement"
                : "SAD \"{$numero}\" : paiement non vérifié. Vérifiez les montants et la domiciliation{$detailsStr}",
            'data' => array_merge($result, [
                'banque_sad_info' => [
                    'id' => $banqueSad->id,
                    'ulid' => $banqueSad->ulid,
                    'num_ddu' => $banqueSad->num_ddu,
                    'num_man' => $banqueSad->num_man,
                    'valeur_caf_ddu' => $banqueSad->valeur_caf_ddu,
                    'valeur_fob_ddu' => $banqueSad->valeur_fob_ddu,
                    'num_dom' => $banqueSad->num_dom,
                    'date_dom' => $banqueSad->date_dom,
                    'bank_dom' => $banqueSad->bank_dom,
                ],
            ]),
        ]);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new BanqueSad())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['num_ddu'] = $update ? 'sometimes|string|max:255' : 'required|string|max:255';

        return $rules;
    }

}
