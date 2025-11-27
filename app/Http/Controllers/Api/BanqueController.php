<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\Banque;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BanqueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $perPage = min($request->integer('per_page', 25), 100);
        $search = $request->string('search')->toString();
        $anneeDvt = $request->integer('annee_dvt');
        $numDvt = $request->string('num_dvt')->toString();
        $refDdu = $request->string('ref_ddu')->toString();
        $dateDebut = $request->string('date_debut')->toString();
        $dateFin = $request->string('date_fin')->toString();

        // Construire la clé de cache avec tous les paramètres de recherche
        $cacheKey = sprintf(
            'banque.index.%s.%s.%s',
            $page,
            $perPage,
            md5($search . $anneeDvt . $numDvt . $refDdu . $dateDebut . $dateFin)
        );

        $payload = CacheTagger::tags(['banque'])->remember($cacheKey, now()->addMinutes(5), function () use ($search, $anneeDvt, $numDvt, $refDdu, $dateDebut, $dateFin, $perPage) {
            $query = Banque::query();

            // Recherche Google-like sur plusieurs champs (selon s360_analyse)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('NUM_DVT', 'ILIKE', "%{$search}%")
                        ->orWhere('REF_DDU', 'ILIKE', "%{$search}%")
                        ->orWhere('CDA_AC', 'ILIKE', "%{$search}%")
                        ->orWhere('ID', 'ILIKE', "%{$search}%");
                });
            }

            // Filtres spécifiques
            if ($anneeDvt) {
                $query->where('ANNEE_DVT', $anneeDvt);
            }

            if ($numDvt) {
                $query->where('NUM_DVT', 'ILIKE', "%{$numDvt}%");
            }

            if ($refDdu) {
                $query->where('REF_DDU', 'ILIKE', "%{$refDdu}%");
            }

            if ($dateDebut) {
                $query->where('DATE_DVT', '>=', $dateDebut);
            }

            if ($dateFin) {
                $query->where('DATE_DVT', '<=', $dateFin);
            }

            // Tri par date DVT décroissante (les plus récentes en premier)
            $query->latest('DATE_DVT');

            $paginator = $query->paginate($perPage);

            // Enrichir chaque enregistrement avec identifiant et message résumé
            $enrichedItems = collect($paginator->items())->map(function ($banque) {
                // Construire l'identifiant de la Banque (priorité: NUM_DVT, puis ID)
                $identifiant = $banque->NUM_DVT ?? $banque->ID ?? "DVT-{$banque->ID}";

                // Construire le message résumé
                $messageParts = [];
                
                if ($banque->NUM_DVT) {
                    $messageParts[] = "DVT: {$banque->NUM_DVT}";
                }
                
                if ($banque->REF_DDU) {
                    $messageParts[] = "DDU: {$banque->REF_DDU}";
                }
                
                if ($banque->MONT_AC_XOF) {
                    $messageParts[] = "Montant AC: " . number_format((float)$banque->MONT_AC_XOF, 0, ',', ' ') . " XOF";
                } elseif ($banque->MONT_FACT_XOF) {
                    $messageParts[] = "Montant Facture: " . number_format((float)$banque->MONT_FACT_XOF, 0, ',', ' ') . " XOF";
                }

                if ($banque->CDA_AC) {
                    $messageParts[] = "CDA: {$banque->CDA_AC}";
                }

                $messageResume = !empty($messageParts) ? implode(' | ', $messageParts) : "DVT #{$banque->ID}";

                // Ajouter les champs enrichis
                $banqueArray = $banque->toArray();
                $banqueArray['identifiant'] = $identifiant;
                $banqueArray['message_resume'] = $messageResume;

                return $banqueArray;
            });

            // Construire le message de réponse
            $total = $paginator->total();
            $message = '';
            
            if ($total > 0) {
                if ($search || $anneeDvt || $numDvt || $refDdu || $dateDebut || $dateFin) {
                    $filters = [];
                    if ($search) $filters[] = "recherche: \"{$search}\"";
                    if ($anneeDvt) $filters[] = "année DVT: {$anneeDvt}";
                    if ($numDvt) $filters[] = "numéro DVT: \"{$numDvt}\"";
                    if ($refDdu) $filters[] = "référence DDU: \"{$refDdu}\"";
                    if ($dateDebut) $filters[] = "date début: {$dateDebut}";
                    if ($dateFin) $filters[] = "date fin: {$dateFin}";
                    
                    $message = "{$total} enregistrement(s) Banque trouvé(s) avec " . implode(', ', $filters);
                } else {
                    $message = "{$total} enregistrement(s) Banque récupéré(s) avec succès";
                }
            } else {
                if ($search || $anneeDvt || $numDvt || $refDdu || $dateDebut || $dateFin) {
                    $filters = [];
                    if ($search) $filters[] = "recherche: \"{$search}\"";
                    if ($anneeDvt) $filters[] = "année DVT: {$anneeDvt}";
                    if ($numDvt) $filters[] = "numéro DVT: \"{$numDvt}\"";
                    if ($refDdu) $filters[] = "référence DDU: \"{$refDdu}\"";
                    if ($dateDebut) $filters[] = "date début: {$dateDebut}";
                    if ($dateFin) $filters[] = "date fin: {$dateFin}";
                    $message = "Aucun enregistrement Banque trouvé pour les filtres: " . implode(', ', $filters);
                } else {
                    $message = "Aucun enregistrement Banque enregistré pour le moment";
                }
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

    public function show(string $id): JsonResponse
    {
        $cacheKey = "banque:show:{$id}";
        
        $payload = CacheTagger::tags(['banque'])->remember($cacheKey, now()->addMinutes(5), function () use ($id) {
            // Récupérer la Banque par ID (ULID)
            $banque = Banque::where('ID', $id)->firstOrFail();

            // Construire l'identifiant (priorité: NUM_DVT, puis ID)
            $identifiant = $banque->NUM_DVT ?? $banque->ID ?? "DVT-{$banque->ID}";

            // Construire le message résumé
            $messageParts = [];
            
            if ($banque->NUM_DVT) {
                $messageParts[] = "DVT: {$banque->NUM_DVT}";
            }
            
            if ($banque->REF_DDU) {
                $messageParts[] = "DDU: {$banque->REF_DDU}";
            }
            
            if ($banque->MONT_AC_XOF) {
                $messageParts[] = "Montant AC: " . number_format((float)$banque->MONT_AC_XOF, 0, ',', ' ') . " XOF";
            } elseif ($banque->MONT_FACT_XOF) {
                $messageParts[] = "Montant Facture: " . number_format((float)$banque->MONT_FACT_XOF, 0, ',', ' ') . " XOF";
            }

            if ($banque->CDA_AC) {
                $messageParts[] = "CDA: {$banque->CDA_AC}";
            }

            if ($banque->ANNEE_DVT) {
                $messageParts[] = "Année: {$banque->ANNEE_DVT}";
            }

            if ($banque->DATE_DVT) {
                $messageParts[] = "Date: " . $banque->DATE_DVT->format('d/m/Y');
            }

            $messageResume = !empty($messageParts) ? implode(' | ', $messageParts) : "DVT #{$banque->ID}";

            // Construire le message de réponse
            $details = [];
            if ($banque->NUM_DVT) $details[] = "DVT: {$banque->NUM_DVT}";
            if ($banque->REF_DDU) $details[] = "DDU: {$banque->REF_DDU}";
            if ($banque->CDA_AC) $details[] = "CDA: {$banque->CDA_AC}";
            if ($banque->ANNEE_DVT) $details[] = "Année: {$banque->ANNEE_DVT}";
            if ($banque->DATE_DVT) $details[] = "Date: " . $banque->DATE_DVT->format('d/m/Y');
            
            $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
            $message = "Détails de la Banque \"{$identifiant}\" récupérés{$detailsStr}";

            // Préparer les données enrichies
            $data = $banque->toArray();
            $data['identifiant'] = $identifiant;
            $data['message_resume'] = $messageResume;

            // Essayer de récupérer la FDI associée si NUM_DVT correspond à numero_fdi
            // Relation: BANQUE.NUM_DVT = fdi_sg.numero_fdi et BANQUE.ANNEE_DVT = fdi_sg.annee
            $fdi = null;
            if ($banque->NUM_DVT && $banque->ANNEE_DVT) {
                $fdi = \App\Models\FdiSg::where('numero_fdi', $banque->NUM_DVT)
                    ->where('annee', $banque->ANNEE_DVT)
                    ->first();
                
                if ($fdi) {
                    $message .= " | FDI liée: {$fdi->numero_fdi}";
                }
            }

            // Ajouter les relations si elles existent
            $data['relations'] = [
                'fdi' => $fdi ? [
                    'ulid' => $fdi->ulid,
                    'numero_fdi' => $fdi->numero_fdi,
                    'numero_fdi_complet' => $fdi->numero_fdi_complet ?? null,
                    'identifiant' => $fdi->identifiant ?? $fdi->numero_fdi,
                    'importateur' => $fdi->importateur ?? null,
                    'valeur_caf' => $fdi->valeur_caf ?? null,
                    'date_fdi' => $fdi->date_fdi ? $fdi->date_fdi->format('Y-m-d\TH:i:s.v\Z') : null,
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

    public function store(StoreBanqueRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // S'assurer que l'ID n'est pas fourni (sera généré automatiquement par HasUlids)
        unset($validated['ID']);
        
        // Créer l'enregistrement (l'ID ULID sera généré automatiquement par HasUlids)
        $banque = Banque::create($validated);

        // Recharger pour obtenir l'ID généré
        $banque->refresh();

        // Construire l'identifiant (priorité: NUM_DVT, puis ID)
        $identifiant = $banque->NUM_DVT ?? $banque->ID ?? "DVT-{$banque->ID}";

        // Construire le message résumé
        $messageParts = [];
        
        if ($banque->NUM_DVT) {
            $messageParts[] = "DVT: {$banque->NUM_DVT}";
        }
        
        if ($banque->REF_DDU) {
            $messageParts[] = "DDU: {$banque->REF_DDU}";
        }
        
        if ($banque->MONT_AC_XOF) {
            $messageParts[] = "Montant AC: " . number_format((float)$banque->MONT_AC_XOF, 0, ',', ' ') . " XOF";
        } elseif ($banque->MONT_FACT_XOF) {
            $messageParts[] = "Montant Facture: " . number_format((float)$banque->MONT_FACT_XOF, 0, ',', ' ') . " XOF";
        }

        if ($banque->CDA_AC) {
            $messageParts[] = "CDA: {$banque->CDA_AC}";
        }

        if ($banque->ANNEE_DVT) {
            $messageParts[] = "Année: {$banque->ANNEE_DVT}";
        }

        $messageResume = !empty($messageParts) ? implode(' | ', $messageParts) : "DVT #{$banque->ID}";

        // Construire le message de succès
        $details = [];
        if ($banque->NUM_DVT) $details[] = "DVT: {$banque->NUM_DVT}";
        if ($banque->ANNEE_DVT) $details[] = "Année: {$banque->ANNEE_DVT}";
        if ($banque->REF_DDU) $details[] = "DDU: {$banque->REF_DDU}";
        if ($banque->CDA_AC) $details[] = "CDA: {$banque->CDA_AC}";
        
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
        $message = "L'enregistrement Banque \"{$identifiant}\" a été créé avec succès{$detailsStr}";

        // Logger l'audit
        AuditService::log('create', "L'enregistrement Banque \"{$identifiant}\" a été créé", 'Banque', $banque->ID, null, $banque->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        // Préparer les données enrichies
        $data = $banque->toArray();
        $data['identifiant'] = $identifiant;
        $data['message_resume'] = $messageResume;

        return response()->json([
            'status' => 201,
            'message' => $message,
            'data' => $data,
        ], 201);
    }

    public function update(UpdateBanqueRequest $request, string $id): JsonResponse
    {
        $banque = Banque::where('ID', $id)->firstOrFail();
        $oldValues = $banque->toArray();
        
        $validated = $request->validated();
        $banque->fill($validated);
        $banque->save();

        // Log the update
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        AuditService::log('update', "L'enregistrement Banque \"{$numero}\" a été modifié", 'Banque', $banque->ID, $oldValues, $banque->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été modifié avec succès",
            'data' => $banque->fresh()->toArray(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $banque = Banque::where('ID', $id)->firstOrFail();
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        $oldValues = $banque->toArray();
        
        $banque->delete();

        // Log the deletion
        AuditService::log('delete', "L'enregistrement Banque \"{$numero}\" a été supprimé", 'Banque', $banque->ID, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été supprimé (peut être restauré)",
            'data' => null,
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'banque:trashed:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Banque::onlyTrashed();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('NUM_DVT', 'ilike', "%{$search}%")
                      ->orWhere('REF_DDU', 'ilike', "%{$search}%")
                      ->orWhere('CDA_AC', 'ilike', "%{$search}%");
                });
            }

            $banques = $query->latest('deleted_at')->paginate($perPage);

            $message = $banques->total() > 0 
                ? "{$banques->total()} enregistrement(s) Banque supprimé(s) trouvé(s)" 
                : 'Aucun enregistrement Banque supprimé';

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

    public function restore(string $id): JsonResponse
    {
        $banque = Banque::onlyTrashed()->where('ID', $id)->firstOrFail();
        $oldValues = $banque->toArray();

        $banque->restore();

        // Log the restoration
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        AuditService::log('restore', "L'enregistrement Banque \"{$numero}\" a été restauré", 'Banque', $banque->ID, $oldValues, $banque->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été restauré avec succès",
            'data' => $banque->fresh()->toArray(),
        ]);
    }

    public function forceDelete(string $id): JsonResponse
    {
        $banque = Banque::onlyTrashed()->where('ID', $id)->firstOrFail();
        $numero = $banque->NUM_DVT ?? "N°{$banque->ID}";
        $oldValues = $banque->toArray();

        // Log the permanent deletion before deleting
        AuditService::log('force_delete', "L'enregistrement Banque \"{$numero}\" a été supprimé définitivement", 'Banque', $banque->ID, $oldValues, []);

        $banque->forceDelete();

        // Invalider le cache
        CacheTagger::tags(['banque'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "L'enregistrement Banque \"{$numero}\" a été supprimé définitivement",
            'data' => null,
        ]);
    }
}

