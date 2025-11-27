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

        $payload = CacheTagger::tags(['fcvr_sg'])->remember($cacheKey, 300, function () use ($search, $perPage, $page) {
            $query = FcvrSg::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_rfcv', 'like', "%{$search}%")
                        ->orWhere('num_fdi', 'like', "%{$search}%")
                        ->orWhere('nom_importateur', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(annee, bureau, 'C', COALESCE(num_rfcv, id)) like ?", ["%{$search}%"]);
                });
            }

            $paginator = $query->latest('date_rfcv')->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()
                ->map(function (FcvrSg $fcvr) {
                    $data = $fcvr->toArray();
                    $data['numero_fcvr_complet'] = $fcvr->numero_fcvr_complet;
                    $data['identifiant'] = $fcvr->identifiant;
                    $data['message_resume'] = sprintf(
                        '%s | Importateur : %s | Valeur CAF : %s %s',
                        $fcvr->numero_fcvr_complet ?? $fcvr->num_rfcv,
                        $fcvr->nom_importateur ?? 'N/A',
                        $fcvr->caf_rfcv_cfa ?? $fcvr->val_fact_rfcv_cfa ?? '0',
                        $fcvr->devise ?? 'XOF'
                    );

                    return $data;
                })
                ->values()
                ->toArray();

            return [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
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
                ? sprintf('Recherche "%s" : %d FCVR trouvée(s). Page %d/%d', $search, $meta['total'], $meta['current_page'], $lastPageDisplay)
                : sprintf('Liste des FCVR : %d enregistrement(s). Page %d/%d', $meta['total'], $meta['current_page'], $lastPageDisplay))
            : ($search
                ? sprintf('Aucun résultat pour "%s". Essayez avec un numéro FCVR, FDI ou importateur', $search)
                : 'Aucune FCVR enregistrée. Créez votre premier enregistrement FCVR');

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $payload['items'],
            'meta' => $meta,
            'links' => $links,
        ]);
    }

    public function store(StoreFcvrSgRequest $request): JsonResponse
    {
        $payload = $request->validated();

        if (empty($payload['instanceid'])) {
            $payload['instanceid'] = (FcvrSg::max('instanceid') ?? FcvrSg::max('id') ?? 0) + 1;
        }

        if (empty($payload['num_tt'])) {
            $payload['num_tt'] = $payload['instanceid'];
        }

        $fcvr = FcvrSg::create($payload);
        $fcvr->refresh();

        // Log the creation
        $numero = $fcvr->num_rfcv ?? "N°{$fcvr->id}";
        AuditService::log('create', "La FCVR \"{$numero}\" a été créée", 'FcvrSg', $fcvr->id, null, $fcvr->toArray());

        CacheTagger::tags(['fcvr_sg'])->flush();

        $data = $fcvr->toArray();
        $data['numero_fcvr_complet'] = $fcvr->numero_fcvr_complet;
        $data['identifiant'] = $fcvr->identifiant;
        $data['message_resume'] = sprintf(
            '%s | Importateur : %s | Valeur CAF : %s %s',
            $fcvr->identifiant,
            $fcvr->nom_importateur ?? 'N/A',
            $fcvr->caf_rfcv_cfa ?? $fcvr->val_fact_rfcv_cfa ?? '0',
            $fcvr->devise ?? 'XOF'
        );

        return response()->json([
            'status' => 201,
            'message' => "La FCVR \"{$fcvr->identifiant}\" a été créée avec succès",
            'data' => $data,
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

        $payload = CacheTagger::tags(['fcvr_sg'])->remember(
            $cacheKey,
            now()->addMinutes(5),
            function () use ($fcvr) {
                $fcvr->load(['articles', 'fdi', 'declaration']);

                $data = $fcvr->toArray();
                $data['numero_fcvr_complet'] = $fcvr->numero_fcvr_complet;
                $data['identifiant'] = $fcvr->identifiant;
                $data['message_resume'] = sprintf(
                    '%s | Importateur : %s | Valeur CAF : %s %s',
                    $fcvr->identifiant,
                    $fcvr->nom_importateur ?? 'N/A',
                    $fcvr->caf_rfcv_cfa ?? $fcvr->val_fact_rfcv_cfa ?? '0',
                    $fcvr->devise ?? 'XOF'
                );

                $relations = [
                    'fdi' => $fcvr->fdi ? $fcvr->fdi->toArray() : null,
                    'declaration' => $fcvr->declaration ? $fcvr->declaration->toArray() : null,
                    'articles' => $fcvr->articles
                        ? $fcvr->articles->map(fn ($article) => $article->toArray())->all()
                        : [],
                ];

                return [
                    'data' => $data,
                    'relations' => $relations,
                ];
            }
        );

        if (!is_array($payload) || !isset($payload['data'])) {
            $data = $fcvr->toArray();
            $data['numero_fcvr_complet'] = $fcvr->numero_fcvr_complet;
            $data['identifiant'] = $fcvr->identifiant;
            $payload = [
                'data' => $data,
                'relations' => [
                    'fdi' => $fcvr->fdi ? $fcvr->fdi->toArray() : null,
                    'declaration' => $fcvr->declaration ? $fcvr->declaration->toArray() : null,
                    'articles' => $fcvr->articles
                        ? $fcvr->articles->map(fn ($article) => $article->toArray())->all()
                        : [],
                ],
            ];

            CacheTagger::tags(['fcvr_sg'])->put($cacheKey, $payload, now()->addMinutes(5));
        }

        $fcvrData = $payload['data'];
        $relations = $payload['relations'];

        $details = [];
        $details[] = 'Importateur : ' . ($fcvrData['nom_importateur'] ?? 'N/A');
        if ($fcvrData['devise']) {
            $details[] = 'Devise : ' . $fcvrData['devise'];
        }
        if ($fcvrData['caf_rfcv_cfa']) {
            $details[] = 'CAF : ' . number_format((float) $fcvrData['caf_rfcv_cfa'], 0, ',', ' ') . ' FCFA';
        }
        if (!empty($relations['declaration'])) {
            $details[] = 'Déclaration liée : ' . ($relations['declaration']['declaration'] ?? '');
        }
        $detailsStr = $details ? ' | ' . implode(' | ', $details) : '';

        return response()->json([
            'status' => 200,
            'message' => "FCVR \"{$fcvrData['identifiant']}\" récupérée avec succès{$detailsStr}",
            'data' => [
                'fcvr' => $fcvrData,
                'relations' => $relations,
            ],
        ]);
    }

    public function update(Request $request, string $fcvrSg): JsonResponse
    {
        $fcvr = $this->findFcvr($fcvrSg);

        $payload = $request->all();
        $oldValues = $fcvr->toArray();

        $fcvr->fill($payload);
        $dirtyFields = array_keys($fcvr->getDirty());
        $fcvr->save();
        $fcvr->refresh();

        $numero = $fcvr->identifiant;
        $details = $dirtyFields
            ? 'Champs modifiés : ' . implode(', ', $dirtyFields)
            : 'Aucun changement détecté';

        AuditService::log(
            'update',
            "La FCVR \"{$numero}\" a été modifiée | {$details}",
            'FcvrSg',
            $fcvr->id,
            $oldValues,
            $fcvr->toArray()
        );

        CacheTagger::tags(['fcvr_sg'])->flush();

        $data = $fcvr->toArray();
        $data['numero_fcvr_complet'] = $fcvr->numero_fcvr_complet;
        $data['identifiant'] = $fcvr->identifiant;
        $data['message_resume'] = sprintf(
            '%s | Importateur : %s | Valeur CAF : %s %s',
            $fcvr->identifiant,
            $fcvr->nom_importateur ?? 'N/A',
            $fcvr->caf_rfcv_cfa ?? $fcvr->val_fact_rfcv_cfa ?? '0',
            $fcvr->devise ?? 'XOF'
        );

        return response()->json([
            'status' => 200,
            'message' => "La FCVR \"{$numero}\" a été mise à jour avec succès | {$details}",
            'data' => $data,
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

        $cacheKey = sprintf('fcvr_sg.%s.declaration', $fcvr->ulid ?? $fcvr->id);

        $payload = CacheTagger::tags(['fcvr_sg', 'declarations'])->remember($cacheKey, 300, function () use ($fcvr) {
            $declaration = $fcvr->declaration;

            if (!$declaration) {
                return null;
            }

            $declarationData = $declaration->toArray();
            $declarationData['identifiant'] = $declaration->identifiant;
            $declarationData['message_resume'] = sprintf(
                '%s | Bureau %s | Valeur CAF : %s %s',
                $declaration->identifiant,
                $declaration->bureau ?? 'N/A',
                number_format((float) ($declaration->valeur_caf_declaration ?? 0), 0, ',', ' '),
                $declaration->devise ?? 'XOF'
            );

            return [
                'fcvr' => [
                    'identifiant' => $fcvr->identifiant,
                    'numero_fcvr_complet' => $fcvr->numero_fcvr_complet,
                    'num_rfcv' => $fcvr->num_rfcv,
                ],
                'declaration' => $declarationData,
            ];
        });

        if (!$payload) {
            return response()->json([
                'status' => 404,
                'message' => sprintf(
                    'Aucune déclaration n’est encore rattachée à la FCVR "%s". Vérifiez le chaînage ANNEE+BUREAU+\'C\'+SEQ décrit dans la note FCVR.',
                    $fcvr->identifiant
                ),
                'data' => null,
            ], 404);
        }

        $message = sprintf(
            'Déclaration "%s" liée à la FCVR "%s" (%s) récupérée avec succès',
            $payload['declaration']['identifiant'],
            $payload['fcvr']['identifiant'],
            $payload['fcvr']['numero_fcvr_complet'] ?? $fcvr->identifiant
        );

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $payload,
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
                'message' => sprintf(
                    'Aucune déclaration trouvée pour la comparaison avec la FCVR "%s" (%s). Vérifiez le chaînage ANNEE+BUREAU+\'C\'+SEQ ou fournissez un declaration_ulid valide.',
                    $fcvr->identifiant,
                    $fcvr->numero_fcvr_complet
                ),
                'data' => null,
            ], 404);
        }

        $cacheKey = sprintf('fcvr_sg.%s.compare.%s', $fcvr->ulid ?? $fcvr->id, $declaration->ulid ?? $declaration->id);

        $comparison = CacheTagger::tags(['fcvr_sg', 'declarations'])->remember($cacheKey, 300, function () use ($fcvr, $declaration) {
            return $this->comparisonService->compare($fcvr, $declaration);
        });

        // Log the comparison
        $fcvrNumero = $fcvr->identifiant;
        $declarationNumero = $declaration->identifiant;
        $summary = $comparison['summary'] ?? [];
        $okCount = $summary['ok'] ?? 0;
        $totalCount = $summary['total'] ?? 0;
        $dangerCount = $summary['danger'] ?? 0;

        AuditService::log(
            'compare',
            sprintf(
                'Comparaison FCVR "%s" (%s) avec déclaration "%s" : %d/%d contrôles OK, %d écart(s) bloquant(s)',
                $fcvrNumero,
                $fcvr->numero_fcvr_complet,
                $declarationNumero,
                $okCount,
                $totalCount,
                $dangerCount
            ),
            'FcvrSg',
            $fcvr->id,
            null,
            [
                'declaration_id' => $declaration->id,
                'declaration_ulid' => $declaration->ulid,
                'summary' => $summary,
            ]
        );

        $message = sprintf(
            'Comparaison FCVR "%s" (%s) / Déclaration "%s" réalisée : %d/%d contrôles OK%s',
            $fcvrNumero,
            $fcvr->numero_fcvr_complet,
            $declarationNumero,
            $okCount,
            $totalCount,
            $dangerCount > 0 ? sprintf(', %d écart(s) bloquant(s) détecté(s)', $dangerCount) : ''
        );

        return response()->json([
            'status' => 200,
            'message' => $message,
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
        $summary = $comparison['summary'] ?? [];
        $totalChecks = $summary['total'] ?? count($comparison['comparisons'] ?? []);
        $dangerCount = $summary['danger'] ?? 0;
        $okCount = $summary['ok'] ?? 0;
        $passed = $dangerCount === 0;
        $completionRate = $totalChecks > 0 ? round(($okCount / $totalChecks) * 100, 1) : 0.0;

        $fcvrInfo = [
            'identifiant' => $fcvr->identifiant,
            'numero_fcvr_complet' => $fcvr->numero_fcvr_complet,
            'annee' => $fcvr->annee,
            'bureau' => $fcvr->bureau,
            'sequence' => $fcvr->num_rfcv,
            'num_fdi' => $fcvr->num_fdi,
            'num_declaration' => $fcvr->num_declaration,
        ];

        $declarationInfo = [
            'identifiant' => $declaration->identifiant,
            'bureau' => $declaration->bureau,
            'date_declaration' => optional($declaration->date_declaration)->toDateString(),
            'ulid' => $declaration->ulid,
        ];

        // Log the validation
        $numero = $fcvr->num_rfcv ?? "N°{$fcvr->id}";
        $declarationNum = $declaration->declaration ?? "N°{$declaration->id}";
        AuditService::log('validate', "Validation de la FCVR \"{$numero}\" avec la déclaration \"{$declarationNum}\"", 'FcvrSg', $fcvr->id, null, [
            'passed' => $passed,
            'declaration_id' => $declaration->id,
            'summary' => $summary,
        ]);

        $message = $passed
            ? sprintf(
                'Chaînage FCVR %s (ANNEE+BUREAU+\'C\'+SEQ) / Déclaration %s validé (%d/%d contrôles OK)',
                $fcvrInfo['numero_fcvr_complet'] ?? $fcvrInfo['identifiant'],
                $declarationInfo['identifiant'],
                $okCount,
                $totalChecks
            )
            : sprintf(
                'Écarts détectés entre la FCVR %s et la déclaration %s : %d contrôle(s) critique(s)',
                $fcvrInfo['numero_fcvr_complet'] ?? $fcvrInfo['identifiant'],
                $declarationInfo['identifiant'],
                $dangerCount
            );

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'fcvr' => $fcvrInfo,
                'declaration' => $declarationInfo,
                'passed' => $passed,
                'summary' => array_merge($summary, [
                    'completion_rate' => $completionRate,
                ]),
                'blocking_issues' => collect($comparison['comparisons'])
                    ->where('status', 'danger')
                    ->values()
                    ->all(),
                'comparisons' => $comparison['comparisons'],
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







