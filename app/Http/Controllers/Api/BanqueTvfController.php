<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BanqueTvf;
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
                    ? "{$paginator->total()} enregistrement(s) TVF correspondant à \"{$search}\" trouvé(s)"
                    : "{$paginator->total()} enregistrement(s) TVF récupéré(s) avec succès")
                : ($search
                    ? "Aucun enregistrement TVF ne correspond à votre recherche \"{$search}\""
                    : "Aucun enregistrement TVF enregistré pour le moment");

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
        $numero = $banqueTvf->num_fdi ?? "N°{$banqueTvf->id}";
        AuditService::log('create', "L'enregistrement TVF \"{$numero}\" a été créé", 'BanqueTvf', $banqueTvf->id, null, $banqueTvf->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'enregistrement TVF \"{$numero}\" a été créé avec succès",
            'data' => $banqueTvf->fresh()->toArray(),
        ], 201);
    }

    public function show(BanqueTvf $banqueTvf): JsonResponse
    {
        $cacheKey = "banque-tvf:show:{$banqueTvf->id}";

        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, 300, function () use ($banqueTvf) {
            $banqueTvf->load(['fdi', 'comparaison1', 'comparaison2']);
            $numero = $banqueTvf->num_fdi ?? "N°{$banqueTvf->id}";

            return [
                'status' => 200,
                'message' => "Détails de l'enregistrement TVF \"{$numero}\" récupérés avec succès",
                'data' => $banqueTvf->toArray(),
            ];
        });

        return response()->json($payload);
    }

    public function update(Request $request, BanqueTvf $banqueTvf): JsonResponse
    {
        $validated = $request->validate($this->rules(true));
        $oldValues = $banqueTvf->toArray();

        $banqueTvf->fill($validated);
        $banqueTvf->save();

        // Log the update
        $numero = $banqueTvf->num_fdi ?? "N°{$banqueTvf->id}";
        AuditService::log('update', "L'enregistrement TVF \"{$numero}\" a été modifié", 'BanqueTvf', $banqueTvf->id, $oldValues, $banqueTvf->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF \"{$numero}\" a été modifié avec succès",
            'data' => $banqueTvf->fresh()->toArray(),
        ]);
    }

    public function destroy(BanqueTvf $banqueTvf): JsonResponse
    {
        $numero = $banqueTvf->num_fdi ?? "N°{$banqueTvf->id}";
        $oldValues = $banqueTvf->toArray();
        
        $banqueTvf->delete();

        // Log the deletion
        AuditService::log('delete', "L'enregistrement TVF \"{$numero}\" a été supprimé", 'BanqueTvf', $banqueTvf->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF \"{$numero}\" a été supprimé avec succès",
            'data' => null,
        ]);
    }

    public function fdi(BanqueTvf $banqueTvf): JsonResponse
    {
        $fdi = $banqueTvf->fdi;
        $numero = $banqueTvf->num_fdi ?? "N°{$banqueTvf->id}";
        
        $message = $fdi
            ? "FDI liée à l'enregistrement TVF \"{$numero}\" récupérée avec succès"
            : "Aucune FDI liée à l'enregistrement TVF \"{$numero}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $fdi ? $fdi->toArray() : null,
        ]);
    }

    public function comparaisons(BanqueTvf $banqueTvf): JsonResponse
    {
        $numero = $banqueTvf->num_fdi ?? "N°{$banqueTvf->id}";
        $comp1 = $banqueTvf->comparaison1;
        $comp2 = $banqueTvf->comparaison2;
        $total = ($comp1 ? 1 : 0) + ($comp2 ? 1 : 0);

        $message = $total > 0
            ? "{$total} comparaison(s) trouvée(s) pour l'enregistrement TVF \"{$numero}\""
            : "Aucune comparaison trouvée pour l'enregistrement TVF \"{$numero}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'comparaison_1' => $comp1 ? $comp1->toArray() : null,
                'comparaison_2' => $comp2 ? $comp2->toArray() : null,
            ],
        ]);
    }

    public function validate(BanqueTvf $banqueTvf): JsonResponse
    {
        $numero = $banqueTvf->num_fdi ?? "N°{$banqueTvf->id}";
        $domiciliationOk = $this->service->verifyDomiciliation($banqueTvf);

        // Log the validation
        AuditService::log('validate', "Validation de l'enregistrement TVF \"{$numero}\" - " . ($domiciliationOk ? 'Domiciliation OK' : 'Domiciliation non vérifiée'), 'BanqueTvf', $banqueTvf->id, null, [
            'domiciliation_ok' => $domiciliationOk,
        ]);

        return response()->json([
            'status' => 200,
            'message' => $domiciliationOk 
                ? "L'enregistrement TVF \"{$numero}\" a une domiciliation valide"
                : "L'enregistrement TVF \"{$numero}\" nécessite une vérification de domiciliation",
            'data' => [
                'domiciliation_ok' => $domiciliationOk,
                'banque_tvf_info' => [
                    'id' => $banqueTvf->id,
                    'num_fdi' => $banqueTvf->num_fdi,
                    'ref_ddu' => $banqueTvf->ref_ddu,
                    'num_dom' => $banqueTvf->num_dom,
                    'date_dom' => $banqueTvf->date_dom,
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




