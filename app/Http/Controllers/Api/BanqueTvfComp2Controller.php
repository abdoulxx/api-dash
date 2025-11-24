<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BanqueTvfComp2;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BanqueTvfComp2Controller extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'banque-tvf-comp-2:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque-tvf-comp-2'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $oldId = $request->string('old_id')->toString();

            $paginator = BanqueTvfComp2::query()
                ->when($oldId, fn ($query) => $query->where('old_id', $oldId))
                ->latest('date_fdi')
                ->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($oldId 
                    ? "{$paginator->total()} enregistrement(s) TVF Comp 2 trouvé(s) pour old_id \"{$oldId}\""
                    : "{$paginator->total()} enregistrement(s) TVF Comp 2 récupéré(s) avec succès")
                : ($oldId
                    ? "Aucun enregistrement TVF Comp 2 trouvé pour old_id \"{$oldId}\""
                    : "Aucun enregistrement TVF Comp 2 enregistré pour le moment");

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

        $record = BanqueTvfComp2::create($validated);

        // Log the creation
        $oldId = $record->old_id ?? "N°{$record->id}";
        AuditService::log('create', "L'enregistrement TVF Comp 2 \"{$oldId}\" a été créé", 'BanqueTvfComp2', $record->id, null, $record->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf-comp-2'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'enregistrement TVF Comp 2 \"{$oldId}\" a été créé avec succès",
            'data' => $record->fresh()->toArray(),
        ], 201);
    }

    public function show(BanqueTvfComp2 $banqueTvfComp2): JsonResponse
    {
        $cacheKey = "banque-tvf-comp-2:show:{$banqueTvfComp2->id}";

        $payload = CacheTagger::tags(['banque-tvf-comp-2'])->remember($cacheKey, 300, function () use ($banqueTvfComp2) {
            $oldId = $banqueTvfComp2->old_id ?? "N°{$banqueTvfComp2->id}";

            return [
                'status' => 200,
                'message' => "Détails de l'enregistrement TVF Comp 2 \"{$oldId}\" récupérés avec succès",
                'data' => $banqueTvfComp2->toArray(),
            ];
        });

        return response()->json($payload);
    }

    public function update(Request $request, BanqueTvfComp2 $banqueTvfComp2): JsonResponse
    {
        $validated = $request->validate($this->rules(true));
        $oldValues = $banqueTvfComp2->toArray();

        $banqueTvfComp2->fill($validated);
        $banqueTvfComp2->save();

        // Log the update
        $oldId = $banqueTvfComp2->old_id ?? "N°{$banqueTvfComp2->id}";
        AuditService::log('update', "L'enregistrement TVF Comp 2 \"{$oldId}\" a été modifié", 'BanqueTvfComp2', $banqueTvfComp2->id, $oldValues, $banqueTvfComp2->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf-comp-2'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF Comp 2 \"{$oldId}\" a été modifié avec succès",
            'data' => $banqueTvfComp2->fresh()->toArray(),
        ]);
    }

    public function destroy(BanqueTvfComp2 $banqueTvfComp2): JsonResponse
    {
        $oldId = $banqueTvfComp2->old_id ?? "N°{$banqueTvfComp2->id}";
        $oldValues = $banqueTvfComp2->toArray();
        
        $banqueTvfComp2->delete();

        // Log the deletion
        AuditService::log('delete', "L'enregistrement TVF Comp 2 \"{$oldId}\" a été supprimé", 'BanqueTvfComp2', $banqueTvfComp2->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque-tvf-comp-2'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF Comp 2 \"{$oldId}\" a été supprimé avec succès",
            'data' => null,
        ]);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new BanqueTvfComp2())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['old_id'] = $update ? 'sometimes|string|max:50' : 'required|string|max:50';

        return $rules;
    }

}




