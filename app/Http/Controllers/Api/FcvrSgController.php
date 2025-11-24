<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFcvrSgRequest;
use App\Models\DeclarationSg;
use App\Models\FcvrSg;
use App\Services\AuditService;
use App\Services\FcvrComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Support\CacheTagger;

class FcvrSgController extends Controller
{
    public function __construct(
        private readonly FcvrComparisonService $comparisonService
    ) {
    }

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
            ], 400);
        }

        $cacheKey = sprintf('fcvr_sg.index.%s.%s', $page, md5($search.$perPage));

        $data = CacheTagger::tags(['fcvr_sg'])->remember($cacheKey, 300, function () use ($search, $perPage, $page) {
            $query = FcvrSg::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_rfcv', 'like', "%{$search}%")
                      ->orWhere('num_fdi', 'like', "%{$search}%")
                      ->orWhere('nom_importateur', 'like', "%{$search}%");
                });
            }

            $fcvr = $query->latest('date_rfcv')->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => $fcvr->items(),
                'meta' => [
                    'current_page' => $fcvr->currentPage(),
                    'last_page' => $fcvr->lastPage(),
                    'per_page' => $fcvr->perPage(),
                    'total' => $fcvr->total(),
                    'from' => $fcvr->firstItem(),
                    'to' => $fcvr->lastItem(),
                ],
                'links' => [
                    'first' => $fcvr->url(1),
                    'last' => $fcvr->url($fcvr->lastPage()),
                    'prev' => $fcvr->previousPageUrl(),
                    'next' => $fcvr->nextPageUrl(),
                ],
            ];
        });

        // Vérifier si la page demandée existe
        if ($page > $data['meta']['last_page'] && $data['meta']['last_page'] > 0) {
            return response()->json([
                'status' => 404,
                'message' => sprintf(
                    'La page %d n\'existe pas. La dernière page disponible est la page %d (sur un total de %d résultat(s))',
                    $page,
                    $data['meta']['last_page'],
                    $data['meta']['total']
                ),
                'data' => [],
                'meta' => $data['meta'],
                'links' => $data['links'] ?? null,
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'FCVR récupérées avec succès',
            'data' => $data['data'],
            'meta' => $data['meta'],
            'links' => $data['links'] ?? null,
        ]);
    }

    public function store(StoreFcvrSgRequest $request): JsonResponse
    {
        $fcvr = FcvrSg::create($request->validated());
        $fcvr->refresh();

        // Log the creation
        $numero = $fcvr->num_rfcv ?? "N°{$fcvr->id}";
        AuditService::log('create', "La FCVR \"{$numero}\" a été créée", 'FcvrSg', $fcvr->id, null, $fcvr->toArray());

        CacheTagger::tags(['fcvr_sg'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "La FCVR \"{$numero}\" a été créée avec succès",
            'data' => $fcvr->toArray(),
        ], 201);
    }

    public function show(string $fcvrSg): JsonResponse
    {
        // Récupérer le FCVR par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($fcvrSg)) {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        } elseif (is_numeric($fcvrSg)) {
            $fcvr = FcvrSg::where('id', $fcvrSg)->firstOrFail();
        } else {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        }
        
        $cacheKey = "fcvr_sg.show." . ($fcvr->ulid ?? $fcvr->id);
        
        $data = CacheTagger::tags(['fcvr_sg'])->remember(
            $cacheKey,
            now()->addMinutes(5),
            function () use ($fcvr) {
                // Charger les relations et retourner toutes les données
                $fcvr->load(['articles', 'fdi', 'declaration']);
                return $fcvr->toArray();
            }
        );

        return response()->json([
            'status' => 200,
            'message' => 'FCVR récupérée avec succès',
            'data' => $data,
        ]);
    }

    public function update(Request $request, string $fcvrSg): JsonResponse
    {
        // Récupérer le FCVR par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($fcvrSg)) {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        } elseif (is_numeric($fcvrSg)) {
            $fcvr = FcvrSg::where('id', $fcvrSg)->firstOrFail();
        } else {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        }
        
        $oldValues = $fcvr->toArray();
        $fcvr->update($request->all());
        $fcvr->refresh();

        // Log the update
        $numero = $fcvr->num_rfcv ?? "N°{$fcvr->id}";
        AuditService::log('update', "La FCVR \"{$numero}\" a été modifiée", 'FcvrSg', $fcvr->id, $oldValues, $fcvr->toArray());

        CacheTagger::tags(['fcvr_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La FCVR \"{$numero}\" a été modifiée avec succès",
            'data' => $fcvr->toArray(),
        ]);
    }

    public function destroy(string $fcvrSg): JsonResponse
    {
        // Récupérer le FCVR par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($fcvrSg)) {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        } elseif (is_numeric($fcvrSg)) {
            $fcvr = FcvrSg::where('id', $fcvrSg)->firstOrFail();
        } else {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        }
        
        $numero = $fcvr->num_rfcv ?? "N°{$fcvr->id}";
        $oldValues = $fcvr->toArray();
        
        $fcvr->delete();

        // Log the deletion
        AuditService::log('delete', "La FCVR \"{$numero}\" a été supprimée (soft delete)", 'FcvrSg', $fcvr->id, $oldValues, null);

        CacheTagger::tags(['fcvr_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "La FCVR \"{$numero}\" a été supprimée avec succès (peut être restaurée)",
            'data' => null
        ]);
    }

    public function articles(string $fcvrSg): JsonResponse
    {
        // Récupérer le FCVR par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($fcvrSg)) {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        } elseif (is_numeric($fcvrSg)) {
            $fcvr = FcvrSg::where('id', $fcvrSg)->firstOrFail();
        } else {
            $fcvr = FcvrSg::where('ulid', $fcvrSg)->firstOrFail();
        }
        
        $articles = $fcvr->articles()->paginate(25);

        return response()->json([
            'status' => 200,
            'message' => 'Articles FCVR récupérés avec succès',
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    public function fdi(string $fcvrSg): JsonResponse
    {
        $fcvr = $this->findFcvr($fcvrSg);

        return response()->json([
            'status' => 200,
            'message' => 'FDI liée récupérée avec succès',
            'data' => $fcvr->fdi,
        ]);
    }

    public function declaration(string $fcvrSg): JsonResponse
    {
        $fcvr = $this->findFcvr($fcvrSg);

        return response()->json([
            'status' => 200,
            'message' => 'Déclaration liée récupérée avec succès',
            'data' => $fcvr->declaration,
        ]);
    }

    public function compare(Request $request, string $fcvrSg): JsonResponse
    {
        $validated = $request->validate([
            'declaration_ulid' => ['nullable', 'string'],
        ]);

        $fcvr = $this->findFcvr($fcvrSg);
        $declaration = $this->resolveDeclaration($fcvr, $validated['declaration_ulid'] ?? null);

        if (!$declaration) {
            return response()->json([
                'status' => 404,
                'message' => 'Déclaration introuvable pour cette comparaison',
                'data' => null,
            ], 404);
        }

        $comparison = $this->comparisonService->compare($fcvr, $declaration);

        return response()->json([
            'status' => 200,
            'message' => 'Comparaison FCVR/Déclaration réalisée avec succès',
            'data' => $comparison,
        ]);
    }

    public function validate(Request $request, string $fcvrSg): JsonResponse
    {
        $validated = $request->validate([
            'declaration_ulid' => ['nullable', 'string'],
        ]);

        $fcvr = $this->findFcvr($fcvrSg);
        $declaration = $this->resolveDeclaration($fcvr, $validated['declaration_ulid'] ?? null);

        if (!$declaration) {
            return response()->json([
                'status' => 404,
                'message' => 'Déclaration introuvable pour cette validation',
                'data' => null,
            ], 404);
        }

        $comparison = $this->comparisonService->compare($fcvr, $declaration);
        $passed = ($comparison['summary']['danger'] ?? 0) === 0;

        // Log the validation
        $numero = $fcvr->num_rfcv ?? "N°{$fcvr->id}";
        $declarationNum = $declaration->declaration ?? "N°{$declaration->id}";
        AuditService::log('validate', "Validation de la FCVR \"{$numero}\" avec la déclaration \"{$declarationNum}\"", 'FcvrSg', $fcvr->id, null, [
            'passed' => $passed,
            'declaration_id' => $declaration->id,
            'summary' => $comparison['summary'],
        ]);

        return response()->json([
            'status' => 200,
            'message' => $passed
                ? "La FCVR \"{$numero}\" est valide : aucune incohérence bloquante"
                : "La FCVR \"{$numero}\" nécessite un contrôle complémentaire",
            'data' => [
                'passed' => $passed,
                'summary' => $comparison['summary'],
                'blocking_issues' => collect($comparison['comparisons'])
                    ->where('status', 'danger')
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function findFcvr(string $identifier): FcvrSg
    {
        $query = FcvrSg::query();

        if (Str::isUlid($identifier)) {
            $fcvr = $query->where('ulid', $identifier)->first();
        } elseif (is_numeric($identifier)) {
            $fcvr = $query->where('id', $identifier)->first();
        } else {
            $fcvr = $query->where('num_rfcv', $identifier)->first();
        }

        if (!$fcvr) {
            abort(404, 'FCVR introuvable');
        }

        return $fcvr;
    }

    private function resolveDeclaration(FcvrSg $fcvr, ?string $declarationIdentifier): ?DeclarationSg
    {
        if ($declarationIdentifier) {
            if (Str::isUlid($declarationIdentifier)) {
                $declaration = DeclarationSg::where('ulid', $declarationIdentifier)->first();
            } else {
                $declaration = DeclarationSg::where('declaration', $declarationIdentifier)->first();
            }

            if ($declaration) {
                return $declaration;
            }
        }

        if ($fcvr->relationLoaded('declaration') && $fcvr->declaration) {
            return $fcvr->declaration;
        }

        if ($fcvr->num_declaration) {
            return DeclarationSg::where('declaration', $fcvr->num_declaration)->first();
        }

        return null;
    }
}

