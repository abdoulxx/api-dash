<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BanqueTvfComp1;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BanqueTvfComp1Controller extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'banque-tvf-comp-1:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque-tvf-comp-1'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $oldId = $request->string('old_id')->toString();

            $paginator = BanqueTvfComp1::query()
                ->when($oldId, fn ($query) => $query->where('old_id', $oldId))
                ->latest('date_fdi')
                ->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($oldId 
                    ? "{$paginator->total()} enregistrement(s) TVF Comp 1 trouvé(s) pour old_id \"{$oldId}\""
                    : "{$paginator->total()} enregistrement(s) TVF Comp 1 récupéré(s) avec succès")
                : ($oldId
                    ? "Aucun enregistrement TVF Comp 1 trouvé pour old_id \"{$oldId}\""
                    : "Aucun enregistrement TVF Comp 1 enregistré pour le moment");

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

        $record = BanqueTvfComp1::create($validated);

        // Log the creation
        $oldId = $record->old_id ?? "N°{$record->id}";
        AuditService::log('create', "L'enregistrement TVF Comp 1 \"{$oldId}\" a été créé", 'BanqueTvfComp1', $record->id, null, $record->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf-comp-1'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'enregistrement TVF Comp 1 \"{$oldId}\" a été créé avec succès",
            'data' => $record->fresh()->toArray(),
        ], 201);
    }

    public function show(BanqueTvfComp1 $banqueTvfComp1): JsonResponse
    {
        $cacheKey = "banque-tvf-comp-1:show:{$banqueTvfComp1->id}";

        $payload = CacheTagger::tags(['banque-tvf-comp-1'])->remember($cacheKey, 300, function () use ($banqueTvfComp1) {
            $oldId = $banqueTvfComp1->old_id ?? "N°{$banqueTvfComp1->id}";

            return [
                'status' => 200,
                'message' => "Détails de l'enregistrement TVF Comp 1 \"{$oldId}\" récupérés avec succès",
                'data' => $banqueTvfComp1->toArray(),
            ];
        });

        return response()->json($payload);
    }

    public function update(Request $request, BanqueTvfComp1 $banqueTvfComp1): JsonResponse
    {
        $validated = $request->validate($this->rules(true));
        $oldValues = $banqueTvfComp1->toArray();

        $banqueTvfComp1->fill($validated);
        $banqueTvfComp1->save();

        // Log the update
        $oldId = $banqueTvfComp1->old_id ?? "N°{$banqueTvfComp1->id}";
        AuditService::log('update', "L'enregistrement TVF Comp 1 \"{$oldId}\" a été modifié", 'BanqueTvfComp1', $banqueTvfComp1->id, $oldValues, $banqueTvfComp1->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf-comp-1'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF Comp 1 \"{$oldId}\" a été modifié avec succès",
            'data' => $banqueTvfComp1->fresh()->toArray(),
        ]);
    }

    public function destroy(BanqueTvfComp1 $banqueTvfComp1): JsonResponse
    {
        $oldId = $banqueTvfComp1->old_id ?? "N°{$banqueTvfComp1->id}";
        $oldValues = $banqueTvfComp1->toArray();
        
        $banqueTvfComp1->delete();

        // Log the deletion
        AuditService::log('delete', "L'enregistrement TVF Comp 1 \"{$oldId}\" a été supprimé", 'BanqueTvfComp1', $banqueTvfComp1->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque-tvf-comp-1'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF Comp 1 \"{$oldId}\" a été supprimé avec succès",
            'data' => null,
        ]);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new BanqueTvfComp1())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['old_id'] = $update ? 'sometimes|string|max:50' : 'required|string|max:50';

        return $rules;
    }

}




