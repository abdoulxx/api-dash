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
                        ->orWhere('num_dom', 'like', "%{$search}%");
                })
                ->latest('date_ddu')
                ->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($search 
                    ? "{$paginator->total()} enregistrement(s) SAD correspondant à \"{$search}\" trouvé(s)"
                    : "{$paginator->total()} enregistrement(s) SAD récupéré(s) avec succès")
                : ($search
                    ? "Aucun enregistrement SAD ne correspond à votre recherche \"{$search}\""
                    : "Aucun enregistrement SAD enregistré pour le moment");

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
        $numero = $banqueSad->num_ddu ?? "N°{$banqueSad->id}";
        AuditService::log('create', "L'enregistrement SAD \"{$numero}\" a été créé", 'BanqueSad', $banqueSad->id, null, $banqueSad->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'enregistrement SAD \"{$numero}\" a été créé avec succès",
            'data' => $banqueSad->fresh()->toArray(),
        ], 201);
    }

    public function show(BanqueSad $banqueSad): JsonResponse
    {
        $cacheKey = "banque-sad:show:{$banqueSad->id}";

        $payload = CacheTagger::tags(['banque-sad'])->remember($cacheKey, 300, function () use ($banqueSad) {
            $banqueSad->load(['declaration', 'manifeste']);
            $numero = $banqueSad->num_ddu ?? "N°{$banqueSad->id}";

            return [
                'status' => 200,
                'message' => "Détails de l'enregistrement SAD \"{$numero}\" récupérés avec succès",
                'data' => $banqueSad->toArray(),
            ];
        });

        return response()->json($payload);
    }

    public function update(Request $request, BanqueSad $banqueSad): JsonResponse
    {
        $validated = $request->validate($this->rules(true));
        $oldValues = $banqueSad->toArray();

        $banqueSad->fill($validated);
        $banqueSad->save();

        // Log the update
        $numero = $banqueSad->num_ddu ?? "N°{$banqueSad->id}";
        AuditService::log('update', "L'enregistrement SAD \"{$numero}\" a été modifié", 'BanqueSad', $banqueSad->id, $oldValues, $banqueSad->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement SAD \"{$numero}\" a été modifié avec succès",
            'data' => $banqueSad->fresh()->toArray(),
        ]);
    }

    public function destroy(BanqueSad $banqueSad): JsonResponse
    {
        $numero = $banqueSad->num_ddu ?? "N°{$banqueSad->id}";
        $oldValues = $banqueSad->toArray();
        
        $banqueSad->delete();

        // Log the deletion
        AuditService::log('delete', "L'enregistrement SAD \"{$numero}\" a été supprimé", 'BanqueSad', $banqueSad->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque-sad'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement SAD \"{$numero}\" a été supprimé avec succès",
            'data' => null,
        ]);
    }

    public function declaration(BanqueSad $banqueSad): JsonResponse
    {
        $declaration = $banqueSad->declaration;
        $numero = $banqueSad->num_ddu ?? "N°{$banqueSad->id}";
        
        $message = $declaration
            ? "Déclaration liée à l'enregistrement SAD \"{$numero}\" récupérée avec succès"
            : "Aucune déclaration liée à l'enregistrement SAD \"{$numero}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $declaration ? $declaration->toArray() : null,
        ]);
    }

    public function manifeste(BanqueSad $banqueSad): JsonResponse
    {
        $manifeste = $banqueSad->manifeste;
        $numero = $banqueSad->num_ddu ?? "N°{$banqueSad->id}";
        
        $message = $manifeste
            ? "Manifeste lié à l'enregistrement SAD \"{$numero}\" récupéré avec succès"
            : "Aucun manifeste lié à l'enregistrement SAD \"{$numero}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $manifeste ? $manifeste->toArray() : null,
        ]);
    }

    public function validate(BanqueSad $banqueSad): JsonResponse
    {
        $numero = $banqueSad->num_ddu ?? "N°{$banqueSad->id}";
        $result = $this->service->verifyPayment($banqueSad);
        $paymentOk = $result['payment_verified'] ?? false;

        // Log the validation
        AuditService::log('validate', "Validation de l'enregistrement SAD \"{$numero}\" - " . ($paymentOk ? 'Paiement vérifié' : 'Paiement non vérifié'), 'BanqueSad', $banqueSad->id, null, $result);

        return response()->json([
            'status' => 200,
            'message' => $paymentOk 
                ? "L'enregistrement SAD \"{$numero}\" a un paiement vérifié"
                : "L'enregistrement SAD \"{$numero}\" nécessite une vérification de paiement",
            'data' => array_merge($result, [
                'banque_sad_info' => [
                    'id' => $banqueSad->id,
                    'num_ddu' => $banqueSad->num_ddu,
                    'num_man' => $banqueSad->num_man,
                    'valeur_caf_ddu' => $banqueSad->valeur_caf_ddu,
                    'valeur_fob_ddu' => $banqueSad->valeur_fob_ddu,
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

        $rules['num_ddu'] = $update ? 'sometimes|string|max:120' : 'required|string|max:120';

        return $rules;
    }

}




