<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BanqueTvfComp1;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BanqueTvfComp1Controller extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $page = max($request->integer('page', 1), 1);
        $search = trim($request->string('search')->toString());
        $oldId = trim($request->string('old_id')->toString());
        $numFdi = trim($request->string('num_fdi')->toString());
        $refDdu = trim($request->string('ref_ddu')->toString());
        $numDom = trim($request->string('num_dom')->toString());
        $statutAc = trim($request->string('statut_ac')->toString());
        $paysExp = trim($request->string('pays_exp')->toString());
        $dateDebut = trim($request->string('date_debut')->toString());
        $dateFin = trim($request->string('date_fin')->toString());

        $cacheKey = sprintf(
            'banque-tvf-comp-1:index:%s.%s.%s',
            $page,
            $perPage,
            md5(implode('|', [
                $search,
                $oldId,
                $numFdi,
                $refDdu,
                $numDom,
                $statutAc,
                $paysExp,
                $dateDebut,
                $dateFin,
            ]))
        );

        $payload = CacheTagger::tags(['banque-tvf-comp-1'])->remember($cacheKey, now()->addMinutes(5), function () use (
            $perPage,
            $search,
            $oldId,
            $numFdi,
            $refDdu,
            $numDom,
            $statutAc,
            $paysExp,
            $dateDebut,
            $dateFin
        ) {
            $query = BanqueTvfComp1::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $like = "%{$search}%";
                    $q->where('old_id', 'ILIKE', $like)
                        ->orWhere('ref_ddu', 'ILIKE', $like)
                        ->orWhere('num_dom', 'ILIKE', $like)
                        ->orWhere('num_demande_ac', 'ILIKE', $like)
                        ->orWhere('statut_ac', 'ILIKE', $like)
                        ->orWhere('bank_dom', 'ILIKE', $like)
                        ->orWhere('pays_exp', 'ILIKE', $like)
                        ->orWhere('autorise_par', 'ILIKE', $like)
                        ->orWhere('code_cda_ac', 'ILIKE', $like)
                        ->orWhere('cda_ac', 'ILIKE', $like)
                        ->orWhereRaw('CAST(num_fdi AS TEXT) ILIKE ?', [$like]);
                });
            }

            if ($oldId) {
                $query->where('old_id', 'ILIKE', "%{$oldId}%");
            }

            if ($numFdi) {
                $query->whereRaw('CAST(num_fdi AS TEXT) ILIKE ?', ["%{$numFdi}%"]);
            }

            if ($refDdu) {
                $query->where('ref_ddu', 'ILIKE', "%{$refDdu}%");
            }

            if ($numDom) {
                $query->where('num_dom', 'ILIKE', "%{$numDom}%");
            }

            if ($statutAc) {
                $query->where('statut_ac', 'ILIKE', "%{$statutAc}%");
            }

            if ($paysExp) {
                $query->where('pays_exp', 'ILIKE', "%{$paysExp}%");
            }

            if ($dateDebut) {
                $query->whereDate('date_fdi', '>=', $dateDebut);
            }

            if ($dateFin) {
                $query->whereDate('date_fdi', '<=', $dateFin);
            }

            $query->orderByDesc('date_fdi')->orderByDesc('old_id');

            $paginator = $query->paginate($perPage);

            $items = collect($paginator->items())->map(function ($record) {
                $identifiantParts = array_filter([
                    $record->old_id ? "COMP1 {$record->old_id}" : null,
                    $record->num_fdi ? "FDI {$record->num_fdi}" : null,
                    $record->ref_ddu ? "DDU {$record->ref_ddu}" : null,
                    $record->num_dom ? "DOM {$record->num_dom}" : null,
                ]);
                $identifiant = !empty($identifiantParts) ? implode(' / ', $identifiantParts) : "COMP1 #{$record->id}";

                $messageParts = [];
                if ($record->statut_ac) {
                    $messageParts[] = "Statut AC : {$record->statut_ac}";
                }
                if ($record->bank_dom) {
                    $messageParts[] = "Banque : {$record->bank_dom}";
                }
                if ($record->mont_ac_xof) {
                    $messageParts[] = "Montant AC : " . number_format((float)$record->mont_ac_xof, 0, ',', ' ') . " XOF";
                } elseif ($record->mont_fact_xof) {
                    $messageParts[] = "Montant facture : " . number_format((float)$record->mont_fact_xof, 0, ',', ' ') . " XOF";
                }
                if ($record->pays_exp) {
                    $messageParts[] = "Pays : {$record->pays_exp}";
                }
                if ($record->date_fdi) {
                    $messageParts[] = "Date FDI : " . $record->date_fdi->format('d/m/Y');
                }

                $recordArray = $record->toArray();
                $recordArray['ulid'] = $record->ulid;
                $recordArray['identifiant'] = $identifiant;
                $recordArray['message_resume'] = !empty($messageParts) ? implode(' | ', $messageParts) : null;
                $recordArray['links'] = [
                    'self' => "/api/banque/tvf-comp-1/{$record->id}",
                    'banque_tvf' => $record->old_id ? "/api/banque/tvf?search={$record->old_id}" : null,
                ];

                return $recordArray;
            });

            $total = $paginator->total();
            $filters = [];
            if ($search) $filters[] = "recherche \"{$search}\"";
            if ($oldId) $filters[] = "old_id {$oldId}";
            if ($numFdi) $filters[] = "FDI {$numFdi}";
            if ($refDdu) $filters[] = "DDU {$refDdu}";
            if ($numDom) $filters[] = "DOM {$numDom}";
            if ($statutAc) $filters[] = "statut AC {$statutAc}";
            if ($paysExp) $filters[] = "pays {$paysExp}";
            if ($dateDebut || $dateFin) {
                $filters[] = "période " . trim(($dateDebut ?: 'N/A') . ' → ' . ($dateFin ?: 'N/A'));
            }

            if ($total > 0) {
                $message = $filters
                    ? "{$total} enregistrement(s) TVF Comp 1 trouvés pour " . implode(', ', $filters) . ". Page {$paginator->currentPage()}/{$paginator->lastPage()}"
                    : "{$total} enregistrement(s) TVF Comp 1 recensés. Page {$paginator->currentPage()}/{$paginator->lastPage()}";
            } else {
                $message = $filters
                    ? "Aucun enregistrement TVF Comp 1 pour " . implode(', ', $filters) . ". Essayez d'autres critères."
                    : "Aucun enregistrement TVF Comp 1 disponible pour le moment.";
            }

            return [
                'status' => 200,
                'message' => $message,
                'data' => $items->toArray(),
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

        $oldId = $record->old_id ?? "N°{$record->id}";

        $messageParts = [];
        if ($record->statut_ac) {
            $messageParts[] = "Statut AC : {$record->statut_ac}";
        }
        if ($record->bank_dom) {
            $messageParts[] = "Banque : {$record->bank_dom}";
        }
        if ($record->mont_ac_xof) {
            $messageParts[] = "Montant AC : " . number_format((float)$record->mont_ac_xof, 0, ',', ' ') . " XOF";
        } elseif ($record->mont_fact_xof) {
            $messageParts[] = "Montant facture : " . number_format((float)$record->mont_fact_xof, 0, ',', ' ') . " XOF";
        }
        if ($record->pays_exp) {
            $messageParts[] = "Pays : {$record->pays_exp}";
        }
        if ($record->date_fdi) {
            $messageParts[] = "Date FDI : " . $record->date_fdi->format('d/m/Y');
        }
        $messageResume = !empty($messageParts) ? implode(' | ', $messageParts) : null;

        $recordData = $record->fresh()->toArray();
        $recordData['ulid'] = $record->ulid;
        $recordData['identifiant'] = $oldId;
        $recordData['message_resume'] = $messageResume;
        $recordData['links'] = [
            'self' => "/api/banque/tvf-comp-1/{$record->id}",
            'banque_tvf' => $record->old_id ? "/api/banque/tvf?search={$record->old_id}" : null,
        ];

        AuditService::log(
            'create',
            "L'enregistrement TVF Comp 1 \"{$oldId}\" a été créé" . ($messageResume ? " | {$messageResume}" : ''),
            'BanqueTvfComp1',
            $record->id,
            null,
            $record->toArray()
        );

        CacheTagger::tags(['banque-tvf-comp-1'])->flush();

        return response()->json([
            'status' => 201,
            'message' => "L'enregistrement TVF Comp 1 \"{$oldId}\" a été créé avec succès" . ($messageResume ? " | {$messageResume}" : ''),
            'data' => $recordData,
        ], 201);
    }

    public function show(string $tvf_comp_1): JsonResponse
    {
        $identifier = $tvf_comp_1;
        $banqueTvfComp1 = BanqueTvfComp1::query()
            ->when(Str::isUlid($identifier), fn ($q) => $q->where('ulid', $identifier))
            ->when(!Str::isUlid($identifier) && is_numeric($identifier), fn ($q) => $q->orWhere('id', (int) $identifier))
            ->orWhere('old_id', $identifier)
            ->firstOrFail();

        $cacheKey = "banque-tvf-comp-1:show:{$banqueTvfComp1->ulid}";

        $payload = CacheTagger::tags(['banque-tvf-comp-1'])->remember($cacheKey, now()->addMinutes(5), function () use ($banqueTvfComp1) {
            $identifiantParts = array_filter([
                $banqueTvfComp1->old_id ? "COMP1 {$banqueTvfComp1->old_id}" : null,
                $banqueTvfComp1->num_fdi ? "FDI {$banqueTvfComp1->num_fdi}" : null,
                $banqueTvfComp1->ref_ddu ? "DDU {$banqueTvfComp1->ref_ddu}" : null,
                $banqueTvfComp1->num_dom ? "DOM {$banqueTvfComp1->num_dom}" : null,
            ]);
            $identifiant = !empty($identifiantParts) ? implode(' / ', $identifiantParts) : ($banqueTvfComp1->ulid ?? "COMP1 #{$banqueTvfComp1->id}");

            $messageParts = [];
            if ($banqueTvfComp1->statut_ac) {
                $messageParts[] = "Statut AC : {$banqueTvfComp1->statut_ac}";
            }
            if ($banqueTvfComp1->bank_dom) {
                $messageParts[] = "Banque : {$banqueTvfComp1->bank_dom}";
            }
            if ($banqueTvfComp1->mont_ac_xof) {
                $messageParts[] = "Montant AC : " . number_format((float)$banqueTvfComp1->mont_ac_xof, 0, ',', ' ') . " XOF";
            } elseif ($banqueTvfComp1->mont_fact_xof) {
                $messageParts[] = "Montant facture : " . number_format((float)$banqueTvfComp1->mont_fact_xof, 0, ',', ' ') . " XOF";
            }
            if ($banqueTvfComp1->pays_exp) {
                $messageParts[] = "Pays : {$banqueTvfComp1->pays_exp}";
            }
            if ($banqueTvfComp1->date_fdi) {
                $messageParts[] = "Date FDI : " . $banqueTvfComp1->date_fdi->format('d/m/Y');
            }

            $data = $banqueTvfComp1->toArray();
            $data['ulid'] = $banqueTvfComp1->ulid;
            $data['identifiant'] = $identifiant;
            $data['message_resume'] = !empty($messageParts) ? implode(' | ', $messageParts) : null;
            $data['links'] = [
                'self' => "/api/banque/tvf-comp-1/{$banqueTvfComp1->ulid}",
                'banque_tvf' => $banqueTvfComp1->old_id ? "/api/banque/tvf?search={$banqueTvfComp1->old_id}" : null,
            ];

            return [
                'status' => 200,
                'message' => "Détails de l'enregistrement TVF Comp 1 \"{$identifiant}\" récupérés avec succès" . (!empty($messageParts) ? " | " . implode(' | ', $messageParts) : ''),
                'data' => $data,
            ];
        });

        return response()->json($payload);
    }

    public function update(Request $request, string $tvf_comp_1): JsonResponse
    {
        $validated = $request->validate($this->rules(true));
        $identifier = $tvf_comp_1;
        $banqueTvfComp1 = BanqueTvfComp1::query()
            ->when(Str::isUlid($identifier), fn ($q) => $q->where('ulid', $identifier))
            ->when(!Str::isUlid($identifier) && is_numeric($identifier), fn ($q) => $q->orWhere('id', (int) $identifier))
            ->orWhere('old_id', $identifier)
            ->firstOrFail();

        $oldValues = $banqueTvfComp1->toArray();

        $banqueTvfComp1->fill($validated);
        $banqueTvfComp1->save();

        CacheTagger::tags(['banque-tvf-comp-1'])->flush();

        $identifiantParts = array_filter([
            $banqueTvfComp1->old_id ? "COMP1 {$banqueTvfComp1->old_id}" : null,
            $banqueTvfComp1->num_fdi ? "FDI {$banqueTvfComp1->num_fdi}" : null,
            $banqueTvfComp1->ref_ddu ? "DDU {$banqueTvfComp1->ref_ddu}" : null,
            $banqueTvfComp1->num_dom ? "DOM {$banqueTvfComp1->num_dom}" : null,
        ]);
        $identifiant = !empty($identifiantParts) ? implode(' / ', $identifiantParts) : ($banqueTvfComp1->ulid ?? "COMP1 #{$banqueTvfComp1->id}");

        $messageParts = [];
        if ($banqueTvfComp1->statut_ac) {
            $messageParts[] = "Statut AC : {$banqueTvfComp1->statut_ac}";
        }
        if ($banqueTvfComp1->bank_dom) {
            $messageParts[] = "Banque : {$banqueTvfComp1->bank_dom}";
        }
        if ($banqueTvfComp1->mont_ac_xof) {
            $messageParts[] = "Montant AC : " . number_format((float)$banqueTvfComp1->mont_ac_xof, 0, ',', ' ') . " XOF";
        } elseif ($banqueTvfComp1->mont_fact_xof) {
            $messageParts[] = "Montant facture : " . number_format((float)$banqueTvfComp1->mont_fact_xof, 0, ',', ' ') . " XOF";
        }
        if ($banqueTvfComp1->pays_exp) {
            $messageParts[] = "Pays : {$banqueTvfComp1->pays_exp}";
        }
        if ($banqueTvfComp1->date_fdi) {
            $messageParts[] = "Date FDI : " . $banqueTvfComp1->date_fdi->format('d/m/Y');
        }

        $changedFields = array_keys(array_diff_assoc($banqueTvfComp1->toArray(), $oldValues));
        $changesSummary = $changedFields
            ? "Champs modifiés (" . count($changedFields) . ") : " . implode(', ', array_slice($changedFields, 0, 6)) . (count($changedFields) > 6 ? '...' : '')
            : "Aucun champ modifié";

        AuditService::log(
            'update',
            "TVF Comp 1 \"{$identifiant}\" mise à jour | {$changesSummary}",
            'BanqueTvfComp1',
            $banqueTvfComp1->id,
            $oldValues,
            $banqueTvfComp1->toArray()
        );

        $data = $banqueTvfComp1->fresh()->toArray();
        $data['ulid'] = $banqueTvfComp1->ulid;
        $data['identifiant'] = $identifiant;
        $data['message_resume'] = !empty($messageParts) ? implode(' | ', $messageParts) : null;
        $data['links'] = [
            'self' => "/api/banque/tvf-comp-1/{$banqueTvfComp1->ulid}",
            'banque_tvf' => $banqueTvfComp1->old_id ? "/api/banque/tvf?search={$banqueTvfComp1->old_id}" : null,
        ];

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF Comp 1 \"{$identifiant}\" a été modifié avec succès | {$changesSummary}" . (!empty($messageParts) ? " | " . implode(' | ', $messageParts) : ''),
            'data' => $data,
        ]);
    }

    public function destroy(string $tvf_comp_1): JsonResponse
    {
        $identifier = $tvf_comp_1;
        $banqueTvfComp1 = BanqueTvfComp1::query()
            ->when(Str::isUlid($identifier), fn ($q) => $q->where('ulid', $identifier))
            ->when(!Str::isUlid($identifier) && is_numeric($identifier), fn ($q) => $q->orWhere('id', (int) $identifier))
            ->orWhere('old_id', $identifier)
            ->firstOrFail();

        $oldValues = $banqueTvfComp1->toArray();

        $identifiantParts = array_filter([
            $banqueTvfComp1->old_id ? "COMP1 {$banqueTvfComp1->old_id}" : null,
            $banqueTvfComp1->num_fdi ? "FDI {$banqueTvfComp1->num_fdi}" : null,
            $banqueTvfComp1->ref_ddu ? "DDU {$banqueTvfComp1->ref_ddu}" : null,
            $banqueTvfComp1->num_dom ? "DOM {$banqueTvfComp1->num_dom}" : null,
        ]);
        $identifiant = !empty($identifiantParts) ? implode(' / ', $identifiantParts) : ($banqueTvfComp1->ulid ?? "COMP1 #{$banqueTvfComp1->id}");

        $banqueTvfComp1->delete();

        CacheTagger::tags(['banque-tvf-comp-1'])->flush();

        $restoreUrl = "/api/banque/tvf-comp-1/{$banqueTvfComp1->ulid}/restore";

        AuditService::log(
            'delete',
            "TVF Comp 1 \"{$identifiant}\" supprimé (soft delete). Restaurable via {$restoreUrl}",
            'BanqueTvfComp1',
            $banqueTvfComp1->id,
            $oldValues,
            null
        );

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement TVF Comp 1 \"{$identifiant}\" a été supprimé. POST {$restoreUrl} pour le restaurer.",
            'data' => [
                'tvf_comp_1' => [
                    'ulid' => $banqueTvfComp1->ulid,
                    'identifiant' => $identifiant,
                    'old_id' => $banqueTvfComp1->old_id,
                    'num_fdi' => $banqueTvfComp1->num_fdi,
                    'statut_ac' => $banqueTvfComp1->statut_ac,
                    'bank_dom' => $banqueTvfComp1->bank_dom,
                    'deleted_at' => now()->format('Y-m-d\TH:i:s.v\Z'),
                    'links' => [
                        'restore' => $restoreUrl,
                    ],
                ],
            ],
        ]);
    }

    public function restore(string $tvf_comp_1): JsonResponse
    {
        $identifier = $tvf_comp_1;
        $banqueTvfComp1 = BanqueTvfComp1::onlyTrashed()
            ->when(Str::isUlid($identifier), fn ($q) => $q->where('ulid', $identifier))
            ->when(!Str::isUlid($identifier) && is_numeric($identifier), fn ($q) => $q->orWhere('id', (int) $identifier))
            ->orWhere('old_id', $identifier)
            ->firstOrFail();

        $oldValues = $banqueTvfComp1->toArray();

        $banqueTvfComp1->restore();

        CacheTagger::tags(['banque-tvf-comp-1'])->flush();

        $deletedAt = $oldValues['deleted_at'] ?? null;

        $identifiantParts = array_filter([
            $banqueTvfComp1->old_id ? "COMP1 {$banqueTvfComp1->old_id}" : null,
            $banqueTvfComp1->num_fdi ? "FDI {$banqueTvfComp1->num_fdi}" : null,
            $banqueTvfComp1->ref_ddu ? "DDU {$banqueTvfComp1->ref_ddu}" : null,
            $banqueTvfComp1->num_dom ? "DOM {$banqueTvfComp1->num_dom}" : null,
        ]);
        $identifiant = !empty($identifiantParts) ? implode(' / ', $identifiantParts) : ($banqueTvfComp1->ulid ?? "COMP1 #{$banqueTvfComp1->id}");

        $messageParts = [];
        if ($banqueTvfComp1->statut_ac) {
            $messageParts[] = "Statut AC : {$banqueTvfComp1->statut_ac}";
        }
        if ($banqueTvfComp1->bank_dom) {
            $messageParts[] = "Banque : {$banqueTvfComp1->bank_dom}";
        }
        if ($banqueTvfComp1->mont_ac_xof) {
            $messageParts[] = "Montant AC : " . number_format((float)$banqueTvfComp1->mont_ac_xof, 0, ',', ' ') . " XOF";
        } elseif ($banqueTvfComp1->mont_fact_xof) {
            $messageParts[] = "Montant facture : " . number_format((float)$banqueTvfComp1->mont_fact_xof, 0, ',', ' ') . " XOF";
        }
        if ($banqueTvfComp1->pays_exp) {
            $messageParts[] = "Pays : {$banqueTvfComp1->pays_exp}";
        }
        if ($banqueTvfComp1->date_fdi) {
            $messageParts[] = "Date FDI : " . $banqueTvfComp1->date_fdi->format('d/m/Y');
        }

        AuditService::log(
            'restore',
            "TVF Comp 1 \"{$identifiant}\" restauré depuis la corbeille",
            'BanqueTvfComp1',
            $banqueTvfComp1->id,
            $oldValues,
            $banqueTvfComp1->toArray()
        );

        $data = $banqueTvfComp1->fresh()->toArray();
        $data['ulid'] = $banqueTvfComp1->ulid;
        $data['identifiant'] = $identifiant;
        $data['message_resume'] = !empty($messageParts) ? implode(' | ', $messageParts) : null;
        $data['restoration'] = [
            'restored_at' => now()->format('Y-m-d\TH:i:s.v\Z'),
            'deleted_at' => $deletedAt,
        ];
        $data['links'] = [
            'self' => "/api/banque/tvf-comp-1/{$banqueTvfComp1->ulid}",
            'delete' => "/api/banque/tvf-comp-1/{$banqueTvfComp1->ulid}",
        ];

        return response()->json([
            'status' => 200,
            'message' => "TVF Comp 1 \"{$identifiant}\" restauré avec succès" . (!empty($messageParts) ? " | " . implode(' | ', $messageParts) : ''),
            'data' => $data,
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




