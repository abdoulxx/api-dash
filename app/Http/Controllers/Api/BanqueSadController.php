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
        $perPage = min($request->integer('per_page', 25), 100);
        $page = max($request->integer('page', 1), 1);
        $search = trim($request->string('search')->toString());
        $numDdu = trim($request->string('num_ddu')->toString());
        $refDdu = trim($request->string('ref_ddu')->toString());
        $numDom = trim($request->string('num_dom')->toString());
        $numMan = trim($request->string('num_man')->toString());
        $statutAc = trim($request->string('statut_ac')->toString());
        $paysExp = trim($request->string('pays_exp')->toString());
        $bankDom = trim($request->string('bank_dom')->toString());
        $dateDebut = trim($request->string('date_debut')->toString());
        $dateFin = trim($request->string('date_fin')->toString());

        $cacheKey = sprintf(
            'banque-sad:index:%s.%s.%s',
            $page,
            $perPage,
            md5(implode('|', [
                $search,
                $numDdu,
                $refDdu,
                $numDom,
                $numMan,
                $statutAc,
                $paysExp,
                $bankDom,
                $dateDebut,
                $dateFin,
            ]))
        );

        $payload = CacheTagger::tags(['banque-sad'])->remember($cacheKey, now()->addMinutes(5), function () use (
            $perPage,
            $search,
            $numDdu,
            $refDdu,
            $numDom,
            $numMan,
            $statutAc,
            $paysExp,
            $bankDom,
            $dateDebut,
            $dateFin
        ) {
            $query = BanqueSad::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_ddu', 'ILIKE', "%{$search}%")
                        ->orWhere('ref_ddu', 'ILIKE', "%{$search}%")
                        ->orWhere('num_dom', 'ILIKE', "%{$search}%")
                        ->orWhere('num_man', 'ILIKE', "%{$search}%")
                        ->orWhere('num_demande_ac', 'ILIKE', "%{$search}%")
                        ->orWhere('statut_ac', 'ILIKE', "%{$search}%")
                        ->orWhere('pays_exp', 'ILIKE', "%{$search}%")
                        ->orWhere('bank_dom', 'ILIKE', "%{$search}%")
                        ->orWhere('banq_enreg', 'ILIKE', "%{$search}%")
                        ->orWhere('ulid', 'ILIKE', "%{$search}%");
                });
            }

            if ($numDdu) {
                $query->where('num_ddu', 'ILIKE', "%{$numDdu}%");
            }

            if ($refDdu) {
                $query->where('ref_ddu', 'ILIKE', "%{$refDdu}%");
            }

            if ($numDom) {
                $query->where('num_dom', 'ILIKE', "%{$numDom}%");
            }

            if ($numMan) {
                $query->where('num_man', 'ILIKE', "%{$numMan}%");
            }

            if ($statutAc) {
                $query->where('statut_ac', 'ILIKE', "%{$statutAc}%");
            }

            if ($paysExp) {
                $query->where('pays_exp', 'ILIKE', "%{$paysExp}%");
            }

            if ($bankDom) {
                $query->where('bank_dom', 'ILIKE', "%{$bankDom}%");
            }

            if ($dateDebut) {
                $query->whereDate('date_ddu', '>=', $dateDebut);
            }

            if ($dateFin) {
                $query->whereDate('date_ddu', '<=', $dateFin);
            }

            $query->orderByDesc('date_ddu')->orderByDesc('num_ddu');

            $paginator = $query->paginate($perPage);

            $enrichedItems = collect($paginator->items())->map(function ($sad) {
                $identifiants = [];
                if ($sad->num_ddu) {
                    $identifiants[] = "DDU {$sad->num_ddu}";
                }
                if ($sad->num_man) {
                    $identifiants[] = "MAN {$sad->num_man}";
                }
                if ($sad->num_dom) {
                    $identifiants[] = "DOM {$sad->num_dom}";
                }
                $identifiant = !empty($identifiants) ? implode(' / ', $identifiants) : ($sad->ulid ?? "SAD #{$sad->id}");

                $messageParts = [];
                if ($sad->statut_ac) {
                    $messageParts[] = "Statut AC: {$sad->statut_ac}";
                }
                if ($sad->pays_exp) {
                    $messageParts[] = "Pays: {$sad->pays_exp}";
                }
                if ($sad->mont_ac_xof) {
                    $messageParts[] = "Montant AC: " . number_format((float)$sad->mont_ac_xof, 0, ',', ' ') . " XOF";
                } elseif ($sad->mont_fact_xof) {
                    $messageParts[] = "Montant Facture: " . number_format((float)$sad->mont_fact_xof, 0, ',', ' ') . " XOF";
                }
                if ($sad->bank_dom) {
                    $messageParts[] = "Banque: {$sad->bank_dom}";
                }
                if ($sad->date_ddu) {
                    $messageParts[] = "Date DDU: " . $sad->date_ddu->format('d/m/Y');
                }

                $sadArray = $sad->toArray();
                $sadArray['identifiant'] = $identifiant;
                $sadArray['message_resume'] = !empty($messageParts) ? implode(' | ', $messageParts) : "SAD #{$sad->id}";
                $sadArray['links'] = [
                    'self' => "/api/banque/sad/{$sad->ulid}",
                    'declaration' => "/api/banque/sad/{$sad->ulid}/declaration",
                    'manifeste' => "/api/banque/sad/{$sad->ulid}/manifeste",
                    'validate' => "/api/banque/sad/{$sad->ulid}/validate",
                ];

                return $sadArray;
            });

            $total = $paginator->total();
            $filtersSummary = [];
            if ($search) $filtersSummary[] = "recherche \"{$search}\"";
            if ($numDdu) $filtersSummary[] = "DDU {$numDdu}";
            if ($refDdu) $filtersSummary[] = "réf. DDU {$refDdu}";
            if ($numDom) $filtersSummary[] = "DOM {$numDom}";
            if ($numMan) $filtersSummary[] = "manifeste {$numMan}";
            if ($statutAc) $filtersSummary[] = "statut AC {$statutAc}";
            if ($paysExp) $filtersSummary[] = "pays {$paysExp}";
            if ($bankDom) $filtersSummary[] = "banque {$bankDom}";
            if ($dateDebut || $dateFin) {
                $filtersSummary[] = "période " . trim(($dateDebut ?: 'N/A') . ' → ' . ($dateFin ?: 'N/A'));
            }

            if ($total > 0) {
                $message = $filtersSummary
                    ? "{$total} SAD trouvée(s) pour " . implode(', ', $filtersSummary) . ". Page {$paginator->currentPage()}/{$paginator->lastPage()}"
                    : "{$total} SAD recensée(s). Page {$paginator->currentPage()}/{$paginator->lastPage()}";
            } else {
                $message = $filtersSummary
                    ? "Aucune SAD pour " . implode(', ', $filtersSummary) . ". Essayez d'autres critères."
                    : "Aucune SAD enregistrée pour le moment.";
            }

            return [
                'status' => 200,
                'message' => $message,
                'data' => $enrichedItems->toArray(),
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

        $identifiants = [];
        if ($banqueSad->num_ddu) {
            $identifiants[] = "DDU {$banqueSad->num_ddu}";
        }
        if ($banqueSad->num_man) {
            $identifiants[] = "MAN {$banqueSad->num_man}";
        }
        if ($banqueSad->num_dom) {
            $identifiants[] = "DOM {$banqueSad->num_dom}";
        }
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";

        $details = [];
        if ($banqueSad->statut_ac) {
            $details[] = "Statut AC : {$banqueSad->statut_ac}";
        }
        if ($banqueSad->bank_dom) {
            $details[] = "Banque : {$banqueSad->bank_dom}";
        }
        if ($banqueSad->mont_ac_xof || $banqueSad->mont_fact_xof) {
            $amount = $banqueSad->mont_ac_xof ?: $banqueSad->mont_fact_xof;
            $label = $banqueSad->mont_ac_xof ? 'Montant AC' : 'Montant Facture';
            $details[] = "{$label} : " . number_format((float)$amount, 0, ',', ' ') . " XOF";
        }
        if ($banqueSad->pays_exp) {
            $details[] = "Pays : {$banqueSad->pays_exp}";
        }
        if ($banqueSad->date_ddu) {
            $details[] = "Date DDU : " . $banqueSad->date_ddu->format('d/m/Y');
        }
        $detailsStr = !empty($details) ? implode(' | ', $details) : null;

        AuditService::log(
            'create',
            "Nouvelle SAD créée : {$numero}" . ($detailsStr ? " | {$detailsStr}" : ''),
            'BanqueSad',
            $banqueSad->id,
            null,
            $banqueSad->toArray()
        );

        CacheTagger::tags(['banque-sad'])->flush();

        $data = $banqueSad->toArray();
        $data['identifiant'] = $numero;
        $data['message_resume'] = $detailsStr;
        $data['links'] = [
            'self' => "/api/banque/sad/{$banqueSad->ulid}",
            'declaration' => "/api/banque/sad/{$banqueSad->ulid}/declaration",
            'manifeste' => "/api/banque/sad/{$banqueSad->ulid}/manifeste",
            'validate' => "/api/banque/sad/{$banqueSad->ulid}/validate",
        ];

        return response()->json([
            'status' => 201,
            'message' => "SAD \"{$numero}\" créée avec succès" . ($detailsStr ? " | {$detailsStr}" : '') . ". ULID : {$banqueSad->ulid}",
            'data' => $data,
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $cacheKey = "banque-sad:show:{$banqueSad->ulid}";

        $payload = CacheTagger::tags(['banque-sad'])->remember($cacheKey, now()->addMinutes(5), function () use ($banqueSad) {
            $banqueSad->load(['declaration', 'manifeste']);

            $identifiants = [];
            if ($banqueSad->num_ddu) {
                $identifiants[] = "DDU {$banqueSad->num_ddu}";
            }
            if ($banqueSad->num_man) {
                $identifiants[] = "MAN {$banqueSad->num_man}";
            }
            if ($banqueSad->num_dom) {
                $identifiants[] = "DOM {$banqueSad->num_dom}";
            }
            $identifiant = !empty($identifiants) ? implode(' / ', $identifiants) : ($banqueSad->ulid ?? "SAD #{$banqueSad->id}");

            $messageParts = [];
            if ($banqueSad->statut_ac) {
                $messageParts[] = "Statut AC: {$banqueSad->statut_ac}";
            }
            if ($banqueSad->pays_exp) {
                $messageParts[] = "Pays: {$banqueSad->pays_exp}";
            }
            if ($banqueSad->mont_ac_xof) {
                $messageParts[] = "Montant AC: " . number_format((float)$banqueSad->mont_ac_xof, 0, ',', ' ') . " XOF";
            } elseif ($banqueSad->mont_fact_xof) {
                $messageParts[] = "Montant Facture: " . number_format((float)$banqueSad->mont_fact_xof, 0, ',', ' ') . " XOF";
            }
            if ($banqueSad->bank_dom) {
                $messageParts[] = "Banque: {$banqueSad->bank_dom}";
            }
            if ($banqueSad->date_ddu) {
                $messageParts[] = "Date DDU: " . $banqueSad->date_ddu->format('d/m/Y');
            }

            $relationsInfo = [];
            if ($banqueSad->declaration) {
                $relationsInfo[] = "Déclaration: {$banqueSad->declaration->declaration}";
            }
            if ($banqueSad->manifeste) {
                $relationsInfo[] = "Manifeste: {$banqueSad->manifeste->num_manifeste}";
            }

            $message = "SAD \"{$identifiant}\" récupérée avec succès";
            if (!empty($messageParts)) {
                $message .= " | " . implode(' | ', $messageParts);
            }
            if (!empty($relationsInfo)) {
                $message .= " | " . implode(' | ', $relationsInfo);
            }

            $data = $banqueSad->toArray();
            $data['identifiant'] = $identifiant;
            $data['message_resume'] = !empty($messageParts) ? implode(' | ', $messageParts) : "SAD #{$banqueSad->id}";

            $data['relations'] = [
                'declaration' => $banqueSad->declaration ? [
                    'ulid' => $banqueSad->declaration->ulid,
                    'declaration' => $banqueSad->declaration->declaration,
                    'numero_declaration_complet' => $banqueSad->declaration->numero_declaration_complet ?? null,
                    'identifiant' => $banqueSad->declaration->identifiant ?? $banqueSad->declaration->declaration,
                    'importateur' => $banqueSad->declaration->importateur ?? null,
                    'date_declaration' => $banqueSad->declaration->date_declaration?->format('d/m/Y'),
                ] : null,
                'manifeste' => $banqueSad->manifeste ? [
                    'ulid' => $banqueSad->manifeste->ulid ?? null,
                    'num_manifeste' => $banqueSad->manifeste->num_manifeste,
                    'numero_manifeste_complet' => $banqueSad->manifeste->numero_manifeste_complet ?? null,
                    'identifiant' => $banqueSad->manifeste->identifiant ?? $banqueSad->manifeste->num_manifeste,
                    'code_bureau' => $banqueSad->manifeste->code_bureau ?? null,
                    'date_manifeste' => $banqueSad->manifeste->date_manifeste?->format('d/m/Y'),
                ] : null,
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

        $identifiants = [];
        if ($banqueSad->num_ddu) {
            $identifiants[] = "DDU {$banqueSad->num_ddu}";
        }
        if ($banqueSad->num_man) {
            $identifiants[] = "MAN {$banqueSad->num_man}";
        }
        if ($banqueSad->num_dom) {
            $identifiants[] = "DOM {$banqueSad->num_dom}";
        }
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";

        $champsModifies = array_keys(array_diff_assoc($banqueSad->toArray(), $oldValues));
        $nbModifs = count($champsModifies);
        $modifsStr = $nbModifs > 0
            ? " ({$nbModifs} champ(s) modifié(s) : " . implode(', ', array_slice($champsModifies, 0, 5)) . ($nbModifs > 5 ? '...' : '') . ")"
            : "";

        AuditService::log('update', "SAD \"{$numero}\" mise à jour{$modifsStr}", 'BanqueSad', $banqueSad->id, $oldValues, $banqueSad->toArray());

        CacheTagger::tags(['banque-sad'])->flush();

        $freshSad = $banqueSad->fresh(['declaration', 'manifeste']);
        $messageParts = [];
        if ($freshSad->statut_ac) {
            $messageParts[] = "Statut AC : {$freshSad->statut_ac}";
        }
        if ($freshSad->bank_dom) {
            $messageParts[] = "Banque : {$freshSad->bank_dom}";
        }
        if ($freshSad->mont_ac_xof) {
            $messageParts[] = "Montant AC : " . number_format((float)$freshSad->mont_ac_xof, 0, ',', ' ') . " XOF";
        } elseif ($freshSad->mont_fact_xof) {
            $messageParts[] = "Montant Facture : " . number_format((float)$freshSad->mont_fact_xof, 0, ',', ' ') . " XOF";
        }
        if ($freshSad->date_ddu) {
            $messageParts[] = "Date DDU : " . $freshSad->date_ddu->format('d/m/Y');
        }

        $data = $freshSad->toArray();
        $data['identifiant'] = $numero;
        $data['message_resume'] = !empty($messageParts) ? implode(' | ', $messageParts) : $numero;
        $data['links'] = [
            'self' => "/api/banque/sad/{$freshSad->ulid}",
            'declaration' => "/api/banque/sad/{$freshSad->ulid}/declaration",
            'manifeste' => "/api/banque/sad/{$freshSad->ulid}/manifeste",
            'validate' => "/api/banque/sad/{$freshSad->ulid}/validate",
        ];
        $data['relations'] = [
            'declaration' => $freshSad->declaration ? [
                'ulid' => $freshSad->declaration->ulid,
                'identifiant' => $freshSad->declaration->identifiant ?? $freshSad->declaration->declaration,
                'numero_declaration_complet' => $freshSad->declaration->numero_declaration_complet ?? null,
                'date_declaration' => $freshSad->declaration->date_declaration?->format('Y-m-d\TH:i:s.v\Z'),
                'importateur' => $freshSad->declaration->importateur ?? null,
            ] : null,
            'manifeste' => $freshSad->manifeste ? [
                'ulid' => $freshSad->manifeste->ulid ?? null,
                'identifiant' => $freshSad->manifeste->identifiant ?? $freshSad->manifeste->num_manifeste,
                'numero_manifeste_complet' => $freshSad->manifeste->numero_manifeste_complet ?? null,
                'date_manifeste' => $freshSad->manifeste->date_manifeste?->format('Y-m-d\TH:i:s.v\Z'),
                'nom_moyen_transport' => $freshSad->manifeste->nom_moyen_transport ?? null,
            ] : null,
        ];

        $contextMessage = "SAD \"{$numero}\" modifiée avec succès{$modifsStr}";
        if (!empty($messageParts)) {
            $contextMessage .= " | " . implode(' | ', $messageParts);
        }
        $contextMessage .= " | Dernière mise à jour : " . now()->format('d/m/Y H:i');

        return response()->json([
            'status' => 200,
            'message' => $contextMessage,
            'data' => $data,
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->with(['declaration', 'manifeste'])->firstOrFail();

        $identifiants = [];
        if ($banqueSad->num_ddu) {
            $identifiants[] = "DDU {$banqueSad->num_ddu}";
        }
        if ($banqueSad->num_man) {
            $identifiants[] = "MAN {$banqueSad->num_man}";
        }
        if ($banqueSad->num_dom) {
            $identifiants[] = "DOM {$banqueSad->num_dom}";
        }
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";
        $oldValues = $banqueSad->toArray();

        $banqueSad->delete();

        $statutInfo = $banqueSad->statut_ac ? "Statut : {$banqueSad->statut_ac}" : null;
        $dateInfo = $banqueSad->date_ddu ? "Date DDU : " . $banqueSad->date_ddu->format('d/m/Y') : null;
        $summaryParts = array_filter([$statutInfo, $dateInfo]);
        $summary = !empty($summaryParts) ? implode(' | ', $summaryParts) : null;

        AuditService::log(
            'delete',
            "SAD \"{$numero}\" supprimée (soft delete). {$summary} | Restaurable via POST /api/banque/sad/{$banqueSad->ulid}/restore",
            'BanqueSad',
            $banqueSad->id,
            $oldValues,
            null
        );

        CacheTagger::tags(['banque-sad'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "SAD \"{$numero}\" envoyée en corbeille. POST /api/banque/sad/{$banqueSad->ulid}/restore permet de la rétablir." . ($summary ? " | {$summary}" : ''),
            'data' => [
                'sad' => [
                    'ulid' => $banqueSad->ulid,
                    'identifiant' => $numero,
                    'num_ddu' => $banqueSad->num_ddu,
                    'num_dom' => $banqueSad->num_dom,
                    'num_man' => $banqueSad->num_man,
                    'statut_ac' => $banqueSad->statut_ac,
                    'bank_dom' => $banqueSad->bank_dom,
                    'message_resume' => $summary,
                    'links' => [
                        'restore' => "/api/banque/sad/{$banqueSad->ulid}/restore",
                        'force_delete' => "/api/banque/sad/{$banqueSad->ulid}/force",
                        'trashed' => "/api/banque/sad/trashed",
                    ],
                ],
                'relations' => [
                    'declaration' => $banqueSad->declaration ? [
                        'ulid' => $banqueSad->declaration->ulid,
                        'identifiant' => $banqueSad->declaration->identifiant ?? $banqueSad->declaration->declaration,
                        'numero_declaration_complet' => $banqueSad->declaration->numero_declaration_complet ?? null,
                        'date_declaration' => $banqueSad->declaration->date_declaration?->format('Y-m-d\TH:i:s.v\Z'),
                    ] : null,
                    'manifeste' => $banqueSad->manifeste ? [
                        'ulid' => $banqueSad->manifeste->ulid ?? null,
                        'identifiant' => $banqueSad->manifeste->identifiant ?? $banqueSad->manifeste->num_manifeste,
                        'numero_manifeste_complet' => $banqueSad->manifeste->numero_manifeste_complet ?? null,
                        'date_manifeste' => $banqueSad->manifeste->date_manifeste?->format('Y-m-d\TH:i:s.v\Z'),
                    ] : null,
                ],
            ],
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

        $banqueSad->load(['declaration', 'manifeste']);

        $identifiants = [];
        if ($banqueSad->num_ddu) {
            $identifiants[] = "DDU {$banqueSad->num_ddu}";
        }
        if ($banqueSad->num_man) {
            $identifiants[] = "MAN {$banqueSad->num_man}";
        }
        if ($banqueSad->num_dom) {
            $identifiants[] = "DOM {$banqueSad->num_dom}";
        }
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "SAD #{$banqueSad->id}";

        $dateSuppression = $banqueSad->deleted_at ? "Supprimée le " . $banqueSad->deleted_at->format('d/m/Y H:i') : "Date de suppression inconnue";
        AuditService::log(
            'restore',
            "SAD \"{$numero}\" restaurée depuis la corbeille ({$dateSuppression})",
            'BanqueSad',
            $banqueSad->id,
            $oldValues,
            $banqueSad->toArray()
        );

        CacheTagger::tags(['banque-sad'])->flush();

        $messageParts = [];
        $messageParts[] = "Statut AC : " . ($banqueSad->statut_ac ?? 'N/A');
        if ($banqueSad->bank_dom) {
            $messageParts[] = "Banque : {$banqueSad->bank_dom}";
        }
        if ($banqueSad->mont_ac_xof || $banqueSad->mont_fact_xof) {
            $amount = $banqueSad->mont_ac_xof ?: $banqueSad->mont_fact_xof;
            $label = $banqueSad->mont_ac_xof ? 'Montant AC' : 'Montant Facture';
            $messageParts[] = "{$label} : " . number_format((float)$amount, 0, ',', ' ') . " XOF";
        }
        if ($banqueSad->date_ddu) {
            $messageParts[] = "Date DDU : " . $banqueSad->date_ddu->format('d/m/Y');
        }

        return response()->json([
            'status' => 200,
            'message' => "SAD \"{$numero}\" restaurée. L'enregistrement redevient actif | " . implode(' | ', $messageParts),
            'data' => [
                'sad' => [
                    'ulid' => $banqueSad->ulid,
                    'identifiant' => $numero,
                    'num_ddu' => $banqueSad->num_ddu,
                    'num_dom' => $banqueSad->num_dom,
                    'num_man' => $banqueSad->num_man,
                    'statut_ac' => $banqueSad->statut_ac,
                    'bank_dom' => $banqueSad->bank_dom,
                    'message_resume' => implode(' | ', $messageParts),
                    'restoration' => [
                        'restored_at' => now()->format('Y-m-d\TH:i:s.v\Z'),
                        'deleted_at' => $banqueSad->getOriginal('deleted_at')?->format('Y-m-d\TH:i:s.v\Z'),
                        'was_deleted' => (bool) $banqueSad->getOriginal('deleted_at'),
                    ],
                    'links' => [
                        'self' => "/api/banque/sad/{$banqueSad->ulid}",
                        'declaration' => "/api/banque/sad/{$banqueSad->ulid}/declaration",
                        'manifeste' => "/api/banque/sad/{$banqueSad->ulid}/manifeste",
                        'validate' => "/api/banque/sad/{$banqueSad->ulid}/validate",
                    ],
                ],
                'relations' => [
                    'declaration' => $banqueSad->declaration ? [
                        'ulid' => $banqueSad->declaration->ulid,
                        'identifiant' => $banqueSad->declaration->identifiant ?? $banqueSad->declaration->declaration,
                        'numero_declaration_complet' => $banqueSad->declaration->numero_declaration_complet ?? null,
                        'date_declaration' => $banqueSad->declaration->date_declaration?->format('Y-m-d\TH:i:s.v\Z'),
                        'importateur' => $banqueSad->declaration->importateur ?? null,
                    ] : null,
                    'manifeste' => $banqueSad->manifeste ? [
                        'ulid' => $banqueSad->manifeste->ulid ?? null,
                        'identifiant' => $banqueSad->manifeste->identifiant ?? $banqueSad->manifeste->num_manifeste,
                        'numero_manifeste_complet' => $banqueSad->manifeste->numero_manifeste_complet ?? null,
                        'date_manifeste' => $banqueSad->manifeste->date_manifeste?->format('Y-m-d\TH:i:s.v\Z'),
                    ] : null,
                ],
            ],
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
        if ($banqueSad->num_ddu) {
            $identifiants[] = "DDU {$banqueSad->num_ddu}";
        }
        if ($banqueSad->num_man) {
            $identifiants[] = "MAN {$banqueSad->num_man}";
        }
        if ($banqueSad->num_dom) {
            $identifiants[] = "DOM {$banqueSad->num_dom}";
        }
        $identifiantSad = !empty($identifiants) ? implode(' / ', $identifiants) : ($banqueSad->ulid ?? "SAD #{$banqueSad->id}");
        
        if (!$declaration) {
            $message = "Aucune déclaration liée à la SAD \"{$identifiantSad}\". Vérifiez la référence DDU ({$banqueSad->ref_ddu}) dans `declaration_sg`.";
            
            return response()->json([
                'status' => 200,
                'message' => $message,
                'data' => [
                    'sad' => [
                        'ulid' => $banqueSad->ulid,
                        'identifiant' => $identifiantSad,
                        'num_ddu' => $banqueSad->num_ddu,
                        'num_dom' => $banqueSad->num_dom,
                        'num_man' => $banqueSad->num_man,
                        'ref_ddu' => $banqueSad->ref_ddu,
                        'statut_ac' => $banqueSad->statut_ac,
                        'pays_exp' => $banqueSad->pays_exp,
                    ],
                    'declaration' => null,
                ],
            ]);
        }

        $messageParts = [];
        $messageParts[] = "Déclaration : {$declaration->declaration}";
        if ($declaration->numero_declaration_complet ?? false) {
            $messageParts[] = "Numéro complet : {$declaration->numero_declaration_complet}";
        }
        if ($declaration->date_declaration) {
            $messageParts[] = "Date : " . $declaration->date_declaration->format('d/m/Y');
        }
        if ($declaration->importateur) {
            $messageParts[] = "Importateur : {$declaration->importateur}";
        }
        if ($declaration->valeur_caf_declaration) {
            $messageParts[] = "Valeur CAF : " . number_format((float)$declaration->valeur_caf_declaration, 0, ',', ' ') . " XOF";
        }

        $message = "Déclaration liée à la SAD \"{$identifiantSad}\" récupérée avec succès | " . implode(' | ', $messageParts);

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'sad' => [
                    'ulid' => $banqueSad->ulid,
                    'identifiant' => $identifiantSad,
                    'num_ddu' => $banqueSad->num_ddu,
                    'num_dom' => $banqueSad->num_dom,
                    'num_man' => $banqueSad->num_man,
                    'ref_ddu' => $banqueSad->ref_ddu,
                    'statut_ac' => $banqueSad->statut_ac,
                    'pays_exp' => $banqueSad->pays_exp,
                    'links' => [
                        'self' => "/api/banque/sad/{$banqueSad->ulid}",
                        'manifeste' => "/api/banque/sad/{$banqueSad->ulid}/manifeste",
                        'validate' => "/api/banque/sad/{$banqueSad->ulid}/validate",
                    ],
                ],
                'declaration' => [
                    'ulid' => $declaration->ulid,
                    'identifiant' => $declaration->identifiant ?? $declaration->declaration,
                    'numero_declaration_complet' => $declaration->numero_declaration_complet ?? null,
                    'declaration' => $declaration->declaration,
                    'date_declaration' => $declaration->date_declaration?->format('Y-m-d\TH:i:s.v\Z'),
                    'importateur' => $declaration->importateur ?? null,
                    'valeur_caf_declaration' => $declaration->valeur_caf_declaration ?? null,
                    'statut' => $declaration->statut ?? null,
                    'links' => [
                        'details' => "/api/declarations/sg/{$declaration->ulid}",
                        'articles' => "/api/declarations/sg/{$declaration->ulid}/articles",
                        'conteneurs' => "/api/declarations/sg/{$declaration->ulid}/conteneurs",
                    ],
                ],
            ],
        ]);
    }

    public function manifeste(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $manifeste = $banqueSad->manifeste;
        
        $identifiants = [];
        if ($banqueSad->num_ddu) {
            $identifiants[] = "DDU {$banqueSad->num_ddu}";
        }
        if ($banqueSad->num_man) {
            $identifiants[] = "MAN {$banqueSad->num_man}";
        }
        if ($banqueSad->num_dom) {
            $identifiants[] = "DOM {$banqueSad->num_dom}";
        }
        $identifiantSad = !empty($identifiants) ? implode(' / ', $identifiants) : ($banqueSad->ulid ?? "SAD #{$banqueSad->id}");
        
        if (!$manifeste) {
            $message = "Aucun manifeste lié à la SAD \"{$identifiantSad}\". Vérifiez le numéro manifeste ({$banqueSad->num_man}) dans `manifeste_sg`.";
            
            return response()->json([
                'status' => 200,
                'message' => $message,
                'data' => [
                    'sad' => [
                        'ulid' => $banqueSad->ulid,
                        'identifiant' => $identifiantSad,
                        'num_ddu' => $banqueSad->num_ddu,
                        'num_dom' => $banqueSad->num_dom,
                        'num_man' => $banqueSad->num_man,
                        'ref_ddu' => $banqueSad->ref_ddu,
                        'statut_ac' => $banqueSad->statut_ac,
                        'pays_exp' => $banqueSad->pays_exp,
                    ],
                    'manifeste' => null,
                ],
            ]);
        }

        $messageParts = [];
        $messageParts[] = "Manifeste : {$manifeste->num_manifeste}";
        if (property_exists($manifeste, 'numero_manifeste_complet') && $manifeste->numero_manifeste_complet) {
            $messageParts[] = "Numéro complet : {$manifeste->numero_manifeste_complet}";
        }
        if ($manifeste->date_manifeste) {
            $messageParts[] = "Date : " . $manifeste->date_manifeste->format('d/m/Y');
        }
        if ($manifeste->nom_moyen_transport ?? false) {
            $messageParts[] = "Navire : {$manifeste->nom_moyen_transport}";
        }
        if ($manifeste->code_bureau ?? false) {
            $messageParts[] = "Bureau : {$manifeste->code_bureau}";
        }

        $message = "Manifeste lié à la SAD \"{$identifiantSad}\" récupéré avec succès | " . implode(' | ', $messageParts);

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'sad' => [
                    'ulid' => $banqueSad->ulid,
                    'identifiant' => $identifiantSad,
                    'num_ddu' => $banqueSad->num_ddu,
                    'num_dom' => $banqueSad->num_dom,
                    'num_man' => $banqueSad->num_man,
                    'ref_ddu' => $banqueSad->ref_ddu,
                    'statut_ac' => $banqueSad->statut_ac,
                    'pays_exp' => $banqueSad->pays_exp,
                    'links' => [
                        'self' => "/api/banque/sad/{$banqueSad->ulid}",
                        'declaration' => "/api/banque/sad/{$banqueSad->ulid}/declaration",
                        'validate' => "/api/banque/sad/{$banqueSad->ulid}/validate",
                    ],
                ],
                'manifeste' => [
                    'ulid' => $manifeste->ulid ?? null,
                    'identifiant' => $manifeste->identifiant ?? $manifeste->num_manifeste,
                    'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                    'num_manifeste' => $manifeste->num_manifeste,
                    'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                    'code_bureau' => $manifeste->code_bureau ?? null,
                    'libelle_bureau' => $manifeste->libelle_bureau ?? null,
                    'num_voyage' => $manifeste->num_voyage ?? null,
                    'nom_moyen_transport' => $manifeste->nom_moyen_transport ?? null,
                    'date_manifeste' => $manifeste->date_manifeste?->format('Y-m-d\TH:i:s.v\Z'),
                    'links' => [
                        'details' => "/api/manifestes/sg/{$manifeste->ulid}",
                        'titres_transport' => "/api/manifestes/sg/{$manifeste->ulid}/titres-transport",
                        'conteneurs' => "/api/manifestes/sg/{$manifeste->ulid}/conteneurs",
                        'declarations' => "/api/manifestes/sg/{$manifeste->ulid}/declarations",
                    ],
                ],
            ],
        ]);
    }

    public function validate(string $ulid): JsonResponse
    {
        $banqueSad = BanqueSad::where('ulid', $ulid)->firstOrFail();
        $banqueSad->loadMissing(['declaration', 'manifeste']);

        $identifiants = [];
        if ($banqueSad->num_ddu) {
            $identifiants[] = "DDU {$banqueSad->num_ddu}";
        }
        if ($banqueSad->num_man) {
            $identifiants[] = "MAN {$banqueSad->num_man}";
        }
        if ($banqueSad->num_dom) {
            $identifiants[] = "DOM {$banqueSad->num_dom}";
        }
        $identifiantSad = !empty($identifiants) ? implode(' / ', $identifiants) : ($banqueSad->ulid ?? "SAD #{$banqueSad->id}");

        $paymentResult = $this->service->verifyPayment($banqueSad);
        $paymentOk = (bool) ($paymentResult['payment_verified'] ?? false);

        $checks = [];

        $checks['identification'] = [
            'label' => 'Identification DDU',
            'fields' => [
                'num_ddu' => $banqueSad->num_ddu,
                'ref_ddu' => $banqueSad->ref_ddu,
                'date_ddu' => $banqueSad->date_ddu,
                'pays_exp' => $banqueSad->pays_exp,
            ],
        ];

        $checks['domiciliation'] = [
            'label' => 'Domiciliation bancaire',
            'fields' => [
                'num_dom' => $banqueSad->num_dom,
                'date_dom' => $banqueSad->date_dom,
                'bank_dom' => $banqueSad->bank_dom,
                'banq_enreg' => $banqueSad->banq_enreg,
            ],
        ];

        $checks['montants'] = [
            'label' => 'Montants financiers',
            'fields' => [
                'mont_ac_xof' => $banqueSad->mont_ac_xof,
                'mont_fact_xof' => $banqueSad->mont_fact_xof,
                'valeur_caf_ddu' => $banqueSad->valeur_caf_ddu,
                'valeur_fob_ddu' => $banqueSad->valeur_fob_ddu,
            ],
        ];

        $checks['relations'] = [
            'label' => 'Relations (Déclaration / Manifeste)',
            'fields' => [
                'declaration' => $banqueSad->declaration?->numero_declaration_complet ?? $banqueSad->declaration?->declaration,
                'manifeste' => $banqueSad->manifeste?->numero_manifeste_complet ?? $banqueSad->manifeste?->num_manifeste,
            ],
        ];

        $checks = collect($checks)->map(function ($check) {
            $missing = [];
            foreach ($check['fields'] as $field => $value) {
                if (is_null($value) || $value === '') {
                    $missing[] = $field;
                }
            }

            return array_merge($check, [
                'missing' => $missing,
                'valid' => empty($missing),
            ]);
        })->toArray();

        $totalFields = array_sum(array_map(fn ($check) => count($check['fields']), $checks));
        $missingFields = array_sum(array_map(fn ($check) => count($check['missing']), $checks));
        $completionRate = $totalFields > 0
            ? round((($totalFields - $missingFields) / $totalFields) * 100, 1)
            : 100.0;

        $overallValid = $paymentOk && $missingFields === 0;

        $messageParts = [];
        $messageParts[] = $overallValid
            ? 'Validation complète'
            : ($paymentOk ? 'Paiement vérifié, complétez les champs manquants' : 'Paiement non vérifié');
        $messageParts[] = "Taux de complétion : {$completionRate}%";
        if ($banqueSad->statut_ac) {
            $messageParts[] = "Statut AC : {$banqueSad->statut_ac}";
        }
        if ($banqueSad->bank_dom) {
            $messageParts[] = "Banque : {$banqueSad->bank_dom}";
        }
        if ($banqueSad->date_dom) {
            $messageParts[] = "Domiciliation : " . $banqueSad->date_dom->format('d/m/Y');
        }

        $message = "Validation de la SAD \"{$identifiantSad}\" | " . implode(' | ', $messageParts);

        AuditService::log('validate', $message, 'BanqueSad', $banqueSad->id, null, [
            'payment_verified' => $paymentOk,
            'completion_rate' => $completionRate,
            'missing_fields' => $missingFields,
        ]);

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'sad' => [
                    'ulid' => $banqueSad->ulid,
                    'identifiant' => $identifiantSad,
                    'num_ddu' => $banqueSad->num_ddu,
                    'num_dom' => $banqueSad->num_dom,
                    'num_man' => $banqueSad->num_man,
                    'ref_ddu' => $banqueSad->ref_ddu,
                    'statut_ac' => $banqueSad->statut_ac,
                    'pays_exp' => $banqueSad->pays_exp,
                    'message_resume' => $banqueSad->statut_ac
                        ? "Statut {$banqueSad->statut_ac} | Banque {$banqueSad->bank_dom} | Montant AC " .
                            ($banqueSad->mont_ac_xof ? number_format((float)$banqueSad->mont_ac_xof, 0, ',', ' ') . ' XOF' : 'N/A')
                        : null,
                    'links' => [
                        'self' => "/api/banque/sad/{$banqueSad->ulid}",
                        'declaration' => "/api/banque/sad/{$banqueSad->ulid}/declaration",
                        'manifeste' => "/api/banque/sad/{$banqueSad->ulid}/manifeste",
                    ],
                ],
                'validation' => [
                    'valid' => $overallValid,
                    'payment_verified' => $paymentOk,
                    'completion_rate' => $completionRate,
                    'checks' => $checks,
                    'payment' => [
                        'details' => $paymentResult,
                        'missing_information' => $paymentOk ? [] : ['Vérifier montants AC/Facture, domiciliation, statut AC'],
                    ],
                    'relations' => [
                        'declaration' => $banqueSad->declaration ? [
                            'ulid' => $banqueSad->declaration->ulid,
                            'identifiant' => $banqueSad->declaration->identifiant ?? $banqueSad->declaration->declaration,
                            'numero_declaration_complet' => $banqueSad->declaration->numero_declaration_complet ?? null,
                            'date_declaration' => $banqueSad->declaration->date_declaration?->format('Y-m-d\TH:i:s.v\Z'),
                            'importateur' => $banqueSad->declaration->importateur ?? null,
                        ] : null,
                        'manifeste' => $banqueSad->manifeste ? [
                            'ulid' => $banqueSad->manifeste->ulid ?? null,
                            'identifiant' => $banqueSad->manifeste->identifiant ?? $banqueSad->manifeste->num_manifeste,
                            'numero_manifeste_complet' => $banqueSad->manifeste->numero_manifeste_complet ?? null,
                            'date_manifeste' => $banqueSad->manifeste->date_manifeste?->format('Y-m-d\TH:i:s.v\Z'),
                        ] : null,
                    ],
                ],
            ],
        ]);
    }

    private function formatPaginator($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
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
