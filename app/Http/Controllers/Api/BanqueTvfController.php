<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BanqueTvf;
use App\Models\FdiSg;
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
        $page = (int) $request->integer('page', 1);
        $perPage = min($request->integer('per_page', 25), 100);
        $search = $request->string('search')->toString();
        $anneeFdi = $request->string('annee_fdi')->toString();
        $numFdi = $request->string('num_fdi')->toString();
        $refDdu = $request->string('ref_ddu')->toString();
        $numDom = $request->string('num_dom')->toString();
        $statutAc = $request->string('statut_ac')->toString();
        $paysExp = $request->string('pays_exp')->toString();
        $dateDebut = $request->string('date_debut')->toString();
        $dateFin = $request->string('date_fin')->toString();

        // Construire la clé de cache avec tous les paramètres de recherche
        $cacheKey = sprintf(
            'banque-tvf.index.%s.%s.%s',
            $page,
            $perPage,
            md5($search . $anneeFdi . $numFdi . $refDdu . $numDom . $statutAc . $paysExp . $dateDebut . $dateFin)
        );

        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, now()->addMinutes(5), function () use ($search, $anneeFdi, $numFdi, $refDdu, $numDom, $statutAc, $paysExp, $dateDebut, $dateFin, $perPage) {
            $query = BanqueTvf::query();

            // Recherche Google-like sur plusieurs champs (selon s360_analyse)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_fdi', 'ILIKE', "%{$search}%")
                        ->orWhere('ref_ddu', 'ILIKE', "%{$search}%")
                        ->orWhere('num_dom', 'ILIKE', "%{$search}%")
                        ->orWhere('num_demande_ac', 'ILIKE', "%{$search}%")
                        ->orWhere('statut_ac', 'ILIKE', "%{$search}%")
                        ->orWhere('pays_exp', 'ILIKE', "%{$search}%")
                        ->orWhere('bank_dom', 'ILIKE', "%{$search}%")
                        ->orWhere('banq_enreg', 'ILIKE', "%{$search}%")
                        ->orWhere('cda_ac', 'ILIKE', "%{$search}%")
                        ->orWhere('ulid', 'ILIKE', "%{$search}%");
                });
            }

            // Filtres spécifiques
            if ($anneeFdi) {
                $query->where('annee_fdi', $anneeFdi);
            }

            if ($numFdi) {
                $query->where('num_fdi', 'ILIKE', "%{$numFdi}%");
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
                $query->where('date_fdi', '>=', $dateDebut);
            }

            if ($dateFin) {
                $query->where('date_fdi', '<=', $dateFin);
            }

            // Tri par date FDI décroissante (les plus récentes en premier)
            $query->latest('date_fdi');

            $paginator = $query->paginate($perPage);

            // Enrichir chaque enregistrement avec identifiant et message résumé
            $enrichedItems = collect($paginator->items())->map(function ($banqueTvf) {
                // Construire l'identifiant composite (selon EXPLICATION_BANQUE_TVF.md)
                $identifiants = [];
                if ($banqueTvf->num_fdi) {
                    $identifiants[] = "FDI {$banqueTvf->num_fdi}";
                }
                if ($banqueTvf->ref_ddu) {
                    $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
                }
                if ($banqueTvf->num_dom) {
                    $identifiants[] = "DOM {$banqueTvf->num_dom}";
                }
                $identifiant = !empty($identifiants) ? implode(' / ', $identifiants) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

                // Construire le message résumé
                $messageParts = [];
                
                if ($banqueTvf->statut_ac) {
                    $messageParts[] = "Statut AC: {$banqueTvf->statut_ac}";
                }
                
                if ($banqueTvf->pays_exp) {
                    $messageParts[] = "Pays: {$banqueTvf->pays_exp}";
                }
                
                if ($banqueTvf->mont_ac_xof) {
                    $messageParts[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
                } elseif ($banqueTvf->mont_fact_xof) {
                    $messageParts[] = "Montant Facture: " . number_format((float)$banqueTvf->mont_fact_xof, 0, ',', ' ') . " XOF";
                }

                if ($banqueTvf->bank_dom) {
                    $messageParts[] = "Banque: {$banqueTvf->bank_dom}";
                }

                if ($banqueTvf->date_fdi) {
                    $messageParts[] = "Date FDI: " . $banqueTvf->date_fdi->format('d/m/Y');
                }

                $messageResume = !empty($messageParts) ? implode(' | ', $messageParts) : "TVF #{$banqueTvf->id}";

                // Ajouter les champs enrichis
                $banqueTvfArray = $banqueTvf->toArray();
                $banqueTvfArray['identifiant'] = $identifiant;
                $banqueTvfArray['message_resume'] = $messageResume;

                return $banqueTvfArray;
            });

            // Construire le message de réponse
            $total = $paginator->total();
            $message = '';
            
            if ($total > 0) {
                if ($search || $anneeFdi || $numFdi || $refDdu || $numDom || $statutAc || $paysExp || $dateDebut || $dateFin) {
                    $filters = [];
                    if ($search) $filters[] = "recherche: \"{$search}\"";
                    if ($anneeFdi) $filters[] = "année FDI: {$anneeFdi}";
                    if ($numFdi) $filters[] = "numéro FDI: \"{$numFdi}\"";
                    if ($refDdu) $filters[] = "référence DDU: \"{$refDdu}\"";
                    if ($numDom) $filters[] = "numéro DOM: \"{$numDom}\"";
                    if ($statutAc) $filters[] = "statut AC: \"{$statutAc}\"";
                    if ($paysExp) $filters[] = "pays exportateur: \"{$paysExp}\"";
                    if ($dateDebut) $filters[] = "date début: {$dateDebut}";
                    if ($dateFin) $filters[] = "date fin: {$dateFin}";
                    
                    $message = "{$total} TVF trouvée(s) avec " . implode(', ', $filters);
                } else {
                    $message = "{$total} TVF récupérée(s) avec succès";
                }
            } else {
                if ($search || $anneeFdi || $numFdi || $refDdu || $numDom || $statutAc || $paysExp || $dateDebut || $dateFin) {
                    $filters = [];
                    if ($search) $filters[] = "recherche: \"{$search}\"";
                    if ($anneeFdi) $filters[] = "année FDI: {$anneeFdi}";
                    if ($numFdi) $filters[] = "numéro FDI: \"{$numFdi}\"";
                    if ($refDdu) $filters[] = "référence DDU: \"{$refDdu}\"";
                    if ($numDom) $filters[] = "numéro DOM: \"{$numDom}\"";
                    if ($statutAc) $filters[] = "statut AC: \"{$statutAc}\"";
                    if ($paysExp) $filters[] = "pays exportateur: \"{$paysExp}\"";
                    if ($dateDebut) $filters[] = "date début: {$dateDebut}";
                    if ($dateFin) $filters[] = "date fin: {$dateFin}";
                    $message = "Aucune TVF trouvée pour les filtres: " . implode(', ', $filters);
                } else {
                    $message = "Aucun enregistrement TVF enregistré pour le moment";
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        // Vérifier que la FDI existe si num_fdi est fourni (selon EXPLICATION_BANQUE_TVF.md)
        if (isset($validated['num_fdi']) && $validated['num_fdi']) {
            $numFdiStr = (string) (int) $validated['num_fdi'];
            $fdiExists = FdiSg::where('numero_fdi', $numFdiStr)->exists();
            if (!$fdiExists) {
                return response()->json([
                    'status' => 422,
                    'message' => "La FDI \"{$validated['num_fdi']}\" n'existe pas. Veuillez créer la FDI avant de créer la TVF.",
                    'errors' => [
                        'num_fdi' => ["La FDI \"{$validated['num_fdi']}\" n'existe pas dans la base de données."],
                    ],
                ], 422);
            }
        }

        // Créer la TVF
        $banqueTvf = BanqueTvf::create($validated);
        $banqueTvf->refresh();

        // Construire l'identifiant composite de la TVF (selon EXPLICATION_BANQUE_TVF.md)
        $identifiantsTvf = [];
        if ($banqueTvf->num_fdi) {
            $identifiantsTvf[] = "FDI {$banqueTvf->num_fdi}";
        }
        if ($banqueTvf->ref_ddu) {
            $identifiantsTvf[] = "DDU {$banqueTvf->ref_ddu}";
        }
        if ($banqueTvf->num_dom) {
            $identifiantsTvf[] = "DOM {$banqueTvf->num_dom}";
        }
        $identifiantTvf = !empty($identifiantsTvf) ? implode(' / ', $identifiantsTvf) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

        // Charger les relations après création
        $banqueTvf->load(['comparaison1', 'comparaison2']);
        $comp1 = $banqueTvf->comparaison1;
        $comp2 = $banqueTvf->comparaison2;
        $fdi = $banqueTvf->fdi;
        
        // Compter les relations
        $comparaisonsCount = ($comp1 ? 1 : 0) + ($comp2 ? 1 : 0);
        $hasFdi = $fdi !== null;

        // Construire les détails pour le message
        $details = [];
        if ($banqueTvf->statut_ac) {
            $details[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $details[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $details[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        if ($hasFdi) {
            $details[] = "FDI liée: {$banqueTvf->num_fdi}";
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';

        // Log the creation
        $relationsInfo = "";
        if ($hasFdi) {
            $relationsInfo .= " | FDI liée";
        }
        if ($comparaisonsCount > 0) {
            $relationsInfo .= " | {$comparaisonsCount} comparaison(s)";
        }
        
        AuditService::log('create', "Nouvelle TVF créée : {$identifiantTvf}{$detailsStr}{$relationsInfo}", 'BanqueTvf', $banqueTvf->id, null, $banqueTvf->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        // Préparer les données de la TVF créée
        $tvfData = $banqueTvf->toArray();
        $tvfData['identifiant'] = $identifiantTvf;
        
        // Construire le message résumé de la TVF
        $messagePartsTvf = [];
        if ($banqueTvf->statut_ac) {
            $messagePartsTvf[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $messagePartsTvf[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $messagePartsTvf[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        $tvfData['message_resume'] = !empty($messagePartsTvf) ? implode(' | ', $messagePartsTvf) : "TVF #{$banqueTvf->id}";

        // Construire le message de réponse enrichi
        $message = "TVF \"{$identifiantTvf}\" créée avec succès{$detailsStr}. ULID : {$banqueTvf->ulid}";

        return response()->json([
            'status' => 201,
            'message' => $message,
            'data' => [
                'tvf' => $tvfData,
                'relations' => [
                    'fdi' => $hasFdi ? [
                        'ulid' => $fdi->ulid,
                        'numero_fdi' => $fdi->numero_fdi,
                        'numero_fdi_complet' => $fdi->numero_fdi_complet ?? null,
                        'identifiant' => $fdi->identifiant ?? $fdi->numero_fdi,
                    ] : null,
                    'comparaisons_count' => $comparaisonsCount,
                    'has_comparaisons' => $comparaisonsCount > 0,
                ],
                'created_at' => $banqueTvf->created_at ? $banqueTvf->created_at->format('Y-m-d\TH:i:s.v\Z') : null,
            ],
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $cacheKey = "banque-tvf:show:{$banqueTvf->ulid}";

        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, now()->addMinutes(5), function () use ($banqueTvf) {
            // Charger les comparaisons
            $banqueTvf->load(['comparaison1', 'comparaison2']);
            
            // La FDI est accessible via l'accessor $banqueTvf->fdi
            $fdi = $banqueTvf->fdi;
            
            // Construire l'identifiant composite (selon EXPLICATION_BANQUE_TVF.md)
            $identifiants = [];
            if ($banqueTvf->num_fdi) {
                $identifiants[] = "FDI {$banqueTvf->num_fdi}";
            }
            if ($banqueTvf->ref_ddu) {
                $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
            }
            if ($banqueTvf->num_dom) {
                $identifiants[] = "DOM {$banqueTvf->num_dom}";
            }
            $identifiant = !empty($identifiants) ? implode(' / ', $identifiants) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");
            
            // Construire le message résumé
            $messageParts = [];
            
            if ($banqueTvf->statut_ac) {
                $messageParts[] = "Statut AC: {$banqueTvf->statut_ac}";
            }
            
            if ($banqueTvf->pays_exp) {
                $messageParts[] = "Pays: {$banqueTvf->pays_exp}";
            }
            
            if ($banqueTvf->mont_ac_xof) {
                $messageParts[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
            } elseif ($banqueTvf->mont_fact_xof) {
                $messageParts[] = "Montant Facture: " . number_format((float)$banqueTvf->mont_fact_xof, 0, ',', ' ') . " XOF";
            }

            if ($banqueTvf->bank_dom) {
                $messageParts[] = "Banque: {$banqueTvf->bank_dom}";
            }

            if ($banqueTvf->date_fdi) {
                $messageParts[] = "Date FDI: " . $banqueTvf->date_fdi->format('d/m/Y');
            }

            $messageResume = !empty($messageParts) ? implode(' | ', $messageParts) : "TVF #{$banqueTvf->id}";
            
            // Construire le message de réponse
            $details = [];
            if ($banqueTvf->statut_ac) $details[] = "Statut : {$banqueTvf->statut_ac}";
            if ($banqueTvf->date_fdi) $details[] = "Date FDI : " . $banqueTvf->date_fdi->format('d/m/Y');
            if ($banqueTvf->pays_exp) $details[] = "Pays : {$banqueTvf->pays_exp}";
            if ($banqueTvf->bank_dom) $details[] = "Banque : {$banqueTvf->bank_dom}";
            $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
            
            $message = "Détails de la TVF \"{$identifiant}\" récupérés{$detailsStr}";
            if ($fdi) {
                $message .= " | FDI liée : {$fdi->numero_fdi}";
            }

            // Retourner toutes les données de l'enregistrement TVF
            // Utiliser toArray() qui applique les casts automatiquement
            $data = $banqueTvf->toArray();
            
            // Retirer les relations si elles sont présentes dans les attributs (elles seront ajoutées séparément)
            unset($data['fdi'], $data['comparaison1'], $data['comparaison2']);
            
            // Ajouter identifiant et message_resume aux données principales
            $data['identifiant'] = $identifiant;
            $data['message_resume'] = $messageResume;
            
            // Ajouter les relations séparément pour plus de clarté
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
                'comparaison1' => $banqueTvf->comparaison1 ? $banqueTvf->comparaison1->toArray() : null,
                'comparaison2' => $banqueTvf->comparaison2 ? $banqueTvf->comparaison2->toArray() : null,
            ];
            
            // S'assurer que deleted_at n'est pas retourné dans les données principales
            if (isset($data['deleted_at']) && $data['deleted_at'] === null) {
                unset($data['deleted_at']);
            }

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
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $validated = $request->validate($this->rules(true));
        $oldValues = $banqueTvf->toArray();

        // Construire l'identifiant composite avant modification (selon EXPLICATION_BANQUE_TVF.md)
        $identifiantsTvfAvant = [];
        if ($banqueTvf->num_fdi) {
            $identifiantsTvfAvant[] = "FDI {$banqueTvf->num_fdi}";
        }
        if ($banqueTvf->ref_ddu) {
            $identifiantsTvfAvant[] = "DDU {$banqueTvf->ref_ddu}";
        }
        if ($banqueTvf->num_dom) {
            $identifiantsTvfAvant[] = "DOM {$banqueTvf->num_dom}";
        }
        $identifiantTvfAvant = !empty($identifiantsTvfAvant) ? implode(' / ', $identifiantsTvfAvant) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

        // Appliquer les modifications
        $banqueTvf->fill($validated);
        $banqueTvf->save();
        $banqueTvf->refresh();

        // Construire l'identifiant composite après modification
        $identifiantsTvf = [];
        if ($banqueTvf->num_fdi) {
            $identifiantsTvf[] = "FDI {$banqueTvf->num_fdi}";
        }
        if ($banqueTvf->ref_ddu) {
            $identifiantsTvf[] = "DDU {$banqueTvf->ref_ddu}";
        }
        if ($banqueTvf->num_dom) {
            $identifiantsTvf[] = "DOM {$banqueTvf->num_dom}";
        }
        $identifiantTvf = !empty($identifiantsTvf) ? implode(' / ', $identifiantsTvf) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

        // Identifier les champs modifiés
        $modifiedFields = [];
        foreach ($validated as $key => $value) {
            if (isset($oldValues[$key]) && $oldValues[$key] != $value) {
                $modifiedFields[] = $key;
            } elseif (!isset($oldValues[$key]) && $value !== null) {
                $modifiedFields[] = $key;
            }
        }
        $nbModifs = count($modifiedFields);
        $modifsStr = $nbModifs > 0 ? " ({$nbModifs} champ(s) modifié(s) : " . implode(', ', array_slice($modifiedFields, 0, 5)) . ($nbModifs > 5 ? '...' : '') . ")" : "";

        // Charger les relations après mise à jour
        $banqueTvf->load(['comparaison1', 'comparaison2']);
        $comp1 = $banqueTvf->comparaison1;
        $comp2 = $banqueTvf->comparaison2;
        $fdi = $banqueTvf->fdi;
        
        // Compter les relations
        $comparaisonsCount = ($comp1 ? 1 : 0) + ($comp2 ? 1 : 0);
        $hasFdi = $fdi !== null;

        // Construire les détails pour le message
        $details = [];
        if ($banqueTvf->statut_ac) {
            $details[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $details[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $details[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        if ($hasFdi) {
            $details[] = "FDI liée: {$banqueTvf->num_fdi}";
        }
        if ($comparaisonsCount > 0) {
            $details[] = "Comparaisons: {$comparaisonsCount}";
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : "";

        // Log the update
        $relationsInfo = "";
        if ($hasFdi) {
            $relationsInfo .= " | FDI liée";
        }
        if ($comparaisonsCount > 0) {
            $relationsInfo .= " | {$comparaisonsCount} comparaison(s)";
        }
        
        AuditService::log('update', "TVF \"{$identifiantTvf}\" mise à jour{$modifsStr}{$relationsInfo}", 'BanqueTvf', $banqueTvf->id, $oldValues, $banqueTvf->toArray());

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        // Préparer les données de la TVF mise à jour
        $tvfData = $banqueTvf->toArray();
        $tvfData['identifiant'] = $identifiantTvf;
        
        // Construire le message résumé de la TVF
        $messagePartsTvf = [];
        if ($banqueTvf->statut_ac) {
            $messagePartsTvf[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $messagePartsTvf[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $messagePartsTvf[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        $tvfData['message_resume'] = !empty($messagePartsTvf) ? implode(' | ', $messagePartsTvf) : "TVF #{$banqueTvf->id}";

        // Construire le message de réponse enrichi
        $message = "TVF \"{$identifiantTvf}\" mise à jour avec succès{$modifsStr}{$detailsStr}. Dernière modification : " . now()->format('d/m/Y H:i');

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'tvf' => $tvfData,
                'modified_fields' => $modifiedFields,
                'modified_count' => $nbModifs,
                'relations' => [
                    'fdi' => $hasFdi ? [
                        'ulid' => $fdi->ulid,
                        'numero_fdi' => $fdi->numero_fdi,
                        'numero_fdi_complet' => $fdi->numero_fdi_complet ?? null,
                        'identifiant' => $fdi->identifiant ?? $fdi->numero_fdi,
                    ] : null,
                    'comparaisons_count' => $comparaisonsCount,
                    'has_comparaisons' => $comparaisonsCount > 0,
                ],
                'updated_at' => $banqueTvf->updated_at ? $banqueTvf->updated_at->format('Y-m-d\TH:i:s.v\Z') : null,
            ],
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        
        // Construire l'identifiant composite de la TVF (selon EXPLICATION_BANQUE_TVF.md)
        $identifiantsTvf = [];
        if ($banqueTvf->num_fdi) {
            $identifiantsTvf[] = "FDI {$banqueTvf->num_fdi}";
        }
        if ($banqueTvf->ref_ddu) {
            $identifiantsTvf[] = "DDU {$banqueTvf->ref_ddu}";
        }
        if ($banqueTvf->num_dom) {
            $identifiantsTvf[] = "DOM {$banqueTvf->num_dom}";
        }
        $identifiantTvf = !empty($identifiantsTvf) ? implode(' / ', $identifiantsTvf) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

        // Charger les relations avant suppression pour les inclure dans le message
        $banqueTvf->load(['comparaison1', 'comparaison2']);
        $comp1 = $banqueTvf->comparaison1;
        $comp2 = $banqueTvf->comparaison2;
        $fdi = $banqueTvf->fdi;
        
        // Compter les relations
        $comparaisonsCount = ($comp1 ? 1 : 0) + ($comp2 ? 1 : 0);
        $hasFdi = $fdi !== null;
        
        $oldValues = $banqueTvf->toArray();
        
        // Effectuer la suppression (soft delete)
        $banqueTvf->delete();

        // Construire les détails pour le message
        $details = [];
        if ($banqueTvf->statut_ac) {
            $details[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $details[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $details[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        if ($banqueTvf->date_fdi) {
            $details[] = "Date FDI: " . $banqueTvf->date_fdi->format('d/m/Y');
        }
        if ($hasFdi) {
            $details[] = "FDI liée: {$banqueTvf->num_fdi}";
        }
        if ($comparaisonsCount > 0) {
            $details[] = "Comparaisons: {$comparaisonsCount}";
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : "";

        // Log the deletion
        $statutInfo = $banqueTvf->statut_ac ? " (Statut : {$banqueTvf->statut_ac})" : '';
        $dateInfo = $banqueTvf->date_fdi ? " | Date FDI : " . $banqueTvf->date_fdi->format('d/m/Y') : '';
        $relationsInfo = "";
        if ($hasFdi) {
            $relationsInfo .= " | FDI liée";
        }
        if ($comparaisonsCount > 0) {
            $relationsInfo .= " | {$comparaisonsCount} comparaison(s)";
        }
        
        AuditService::log('delete', "TVF \"{$identifiantTvf}\"{$statutInfo}{$dateInfo}{$relationsInfo} supprimée (soft delete). Restauration possible via /restore", 'BanqueTvf', $banqueTvf->id, $oldValues, null);

        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        // Construire le message de réponse enrichi
        $message = "TVF \"{$identifiantTvf}\" supprimée (soft delete){$detailsStr}. POST /api/banque/tvf/{$banqueTvf->ulid}/restore permet de la rétablir.";

        // Préparer les données de la TVF supprimée pour la réponse
        $tvfData = $banqueTvf->toArray();
        $tvfData['identifiant'] = $identifiantTvf;
        
        // Construire le message résumé de la TVF
        $messagePartsTvf = [];
        if ($banqueTvf->statut_ac) {
            $messagePartsTvf[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $messagePartsTvf[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $messagePartsTvf[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        $tvfData['message_resume'] = !empty($messagePartsTvf) ? implode(' | ', $messagePartsTvf) : "TVF #{$banqueTvf->id}";
        $tvfData['deleted_at'] = $banqueTvf->deleted_at ? $banqueTvf->deleted_at->format('Y-m-d\TH:i:s.v\Z') : null;

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'tvf' => $tvfData,
                'relations' => [
                    'fdi' => $hasFdi ? [
                        'ulid' => $fdi->ulid,
                        'numero_fdi' => $fdi->numero_fdi,
                        'numero_fdi_complet' => $fdi->numero_fdi_complet ?? null,
                        'identifiant' => $fdi->identifiant ?? $fdi->numero_fdi,
                    ] : null,
                    'comparaisons_count' => $comparaisonsCount,
                    'has_comparaisons' => $comparaisonsCount > 0,
                ],
                'restore_url' => "/api/banque/tvf/{$banqueTvf->ulid}/restore",
            ],
        ]);
    }
    
    public function trashed(Request $request): JsonResponse
    {
        $cacheKey = 'banque-tvf:trashed:' . md5($request->fullUrl());
        
        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, 300, function () use ($request) {
            $perPage = min($request->integer('per_page', 15), 100);
            $search = $request->string('search')->toString();
            
            $query = BanqueTvf::onlyTrashed();
            
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_fdi', 'like', "%{$search}%")
                      ->orWhere('ref_ddu', 'like', "%{$search}%")
                      ->orWhere('num_dom', 'like', "%{$search}%");
                });
            }
            
            $banques = $query->latest('deleted_at')->paginate($perPage);
            
            $message = $banques->total() > 0
                ? "{$banques->total()} TVF supprimée(s) recensée(s). Page {$banques->currentPage()}/{$banques->lastPage()}"
                : "Aucune TVF en corbeille.";
            
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
    
    public function restore(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        $oldValues = $banqueTvf->toArray();
        
        // Sauvegarder la date de suppression avant restauration
        $dateSuppression = $banqueTvf->deleted_at ? $banqueTvf->deleted_at->format('d/m/Y H:i') : null;
        
        // Restaurer la TVF
        $banqueTvf->restore();
        $banqueTvf->refresh();
        
        // Construire l'identifiant composite de la TVF (selon EXPLICATION_BANQUE_TVF.md)
        $identifiantsTvf = [];
        if ($banqueTvf->num_fdi) {
            $identifiantsTvf[] = "FDI {$banqueTvf->num_fdi}";
        }
        if ($banqueTvf->ref_ddu) {
            $identifiantsTvf[] = "DDU {$banqueTvf->ref_ddu}";
        }
        if ($banqueTvf->num_dom) {
            $identifiantsTvf[] = "DOM {$banqueTvf->num_dom}";
        }
        $identifiantTvf = !empty($identifiantsTvf) ? implode(' / ', $identifiantsTvf) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

        // Charger les relations après restauration
        $banqueTvf->load(['comparaison1', 'comparaison2']);
        $comp1 = $banqueTvf->comparaison1;
        $comp2 = $banqueTvf->comparaison2;
        $fdi = $banqueTvf->fdi;
        
        // Compter les relations
        $comparaisonsCount = ($comp1 ? 1 : 0) + ($comp2 ? 1 : 0);
        $hasFdi = $fdi !== null;

        // Construire les détails pour le message
        $details = [];
        if ($banqueTvf->statut_ac) {
            $details[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $details[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $details[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        if ($dateSuppression) {
            $details[] = "Supprimée le: {$dateSuppression}";
        }
        if ($hasFdi) {
            $details[] = "FDI liée: {$banqueTvf->num_fdi}";
        }
        if ($comparaisonsCount > 0) {
            $details[] = "Comparaisons: {$comparaisonsCount}";
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : "";

        // Log the restoration
        $dateSuppressionInfo = $dateSuppression ? " (supprimée le {$dateSuppression})" : "";
        $relationsInfo = "";
        if ($hasFdi) {
            $relationsInfo .= " | FDI liée";
        }
        if ($comparaisonsCount > 0) {
            $relationsInfo .= " | {$comparaisonsCount} comparaison(s)";
        }
        
        AuditService::log('restore', "TVF \"{$identifiantTvf}\" restaurée depuis la corbeille{$dateSuppressionInfo}{$relationsInfo}", 'BanqueTvf', $banqueTvf->id, $oldValues, $banqueTvf->toArray());
        
        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();

        // Préparer les données de la TVF restaurée
        $tvfData = $banqueTvf->toArray();
        $tvfData['identifiant'] = $identifiantTvf;
        
        // Construire le message résumé de la TVF
        $messagePartsTvf = [];
        if ($banqueTvf->statut_ac) {
            $messagePartsTvf[] = "Statut AC: {$banqueTvf->statut_ac}";
        }
        if ($banqueTvf->pays_exp) {
            $messagePartsTvf[] = "Pays: {$banqueTvf->pays_exp}";
        }
        if ($banqueTvf->mont_ac_xof) {
            $messagePartsTvf[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
        }
        $tvfData['message_resume'] = !empty($messagePartsTvf) ? implode(' | ', $messagePartsTvf) : "TVF #{$banqueTvf->id}";

        // Construire le message de réponse enrichi
        $message = "TVF \"{$identifiantTvf}\" restaurée avec succès{$detailsStr}. L'enregistrement redevient actif.";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'tvf' => $tvfData,
                'relations' => [
                    'fdi' => $hasFdi ? [
                        'ulid' => $fdi->ulid,
                        'numero_fdi' => $fdi->numero_fdi,
                        'numero_fdi_complet' => $fdi->numero_fdi_complet ?? null,
                        'identifiant' => $fdi->identifiant ?? $fdi->numero_fdi,
                    ] : null,
                    'comparaisons_count' => $comparaisonsCount,
                    'has_comparaisons' => $comparaisonsCount > 0,
                ],
                'restoration_info' => [
                    'restored_at' => now()->format('Y-m-d\TH:i:s.v\Z'),
                    'deleted_at' => $dateSuppression,
                    'was_deleted' => $dateSuppression !== null,
                ],
            ],
        ]);
    }
    
    public function forceDelete(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::onlyTrashed()->where('ulid', $ulid)->firstOrFail();
        
        $identifiants = [];
        if ($banqueTvf->num_fdi) $identifiants[] = "FDI {$banqueTvf->num_fdi}";
        if ($banqueTvf->ref_ddu) $identifiants[] = "DDU {$banqueTvf->ref_ddu}";
        $numero = !empty($identifiants) ? implode(' / ', $identifiants) : "TVF #{$banqueTvf->id}";
        $oldValues = $banqueTvf->toArray();
        
        // Log the permanent deletion before deleting
        AuditService::log('force_delete', "TVF \"{$numero}\" supprimée définitivement (suppression permanente)", 'BanqueTvf', $banqueTvf->id, $oldValues, []);
        
        $banqueTvf->forceDelete();
        
        // Invalider le cache
        CacheTagger::tags(['banque-tvf'])->flush();
        
        return response()->json([
            'status' => 200,
            'message' => "TVF \"{$numero}\" supprimée définitivement. Action irréversible.",
            'data' => null,
        ]);
    }

    public function fdi(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $cacheKey = "banque-tvf:fdi:{$banqueTvf->ulid}";

        $payload = CacheTagger::tags(['banque-tvf', 'fdi'])->remember($cacheKey, now()->addMinutes(5), function () use ($banqueTvf) {
            // Récupérer la FDI manuellement car la relation nécessite une conversion de type
            $fdi = null;
            if ($banqueTvf->num_fdi) {
                $numFdiStr = (string) (int) $banqueTvf->num_fdi;
                $fdi = FdiSg::where('numero_fdi', $numFdiStr)->first();
            }
            
            // Construire l'identifiant composite de la TVF (selon EXPLICATION_BANQUE_TVF.md)
            $identifiantsTvf = [];
            if ($banqueTvf->num_fdi) {
                $identifiantsTvf[] = "FDI {$banqueTvf->num_fdi}";
            }
            if ($banqueTvf->ref_ddu) {
                $identifiantsTvf[] = "DDU {$banqueTvf->ref_ddu}";
            }
            if ($banqueTvf->num_dom) {
                $identifiantsTvf[] = "DOM {$banqueTvf->num_dom}";
            }
            $identifiantTvf = !empty($identifiantsTvf) ? implode(' / ', $identifiantsTvf) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

            // Construire le message résumé de la TVF
            $messagePartsTvf = [];
            if ($banqueTvf->statut_ac) {
                $messagePartsTvf[] = "Statut AC: {$banqueTvf->statut_ac}";
            }
            if ($banqueTvf->pays_exp) {
                $messagePartsTvf[] = "Pays: {$banqueTvf->pays_exp}";
            }
            if ($banqueTvf->mont_ac_xof) {
                $messagePartsTvf[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
            }
            $messageResumeTvf = !empty($messagePartsTvf) ? implode(' | ', $messagePartsTvf) : "TVF #{$banqueTvf->id}";

            // Préparer les données de la TVF
            $tvfData = $banqueTvf->toArray();
            $tvfData['identifiant'] = $identifiantTvf;
            $tvfData['message_resume'] = $messageResumeTvf;

            // Préparer les données de la FDI si elle existe
            $fdiData = null;
            if ($fdi) {
                $fdiData = [
                    'ulid' => $fdi->ulid,
                    'numero_fdi' => $fdi->numero_fdi,
                    'numero_fdi_complet' => $fdi->numero_fdi_complet ?? null,
                    'identifiant' => $fdi->identifiant ?? $fdi->numero_fdi,
                    'annee' => $fdi->annee ?? null,
                    'bureau' => $fdi->bureau ?? null,
                    'serie_fdi' => $fdi->serie_fdi ?? null,
                    'numero_serie' => $fdi->numero_serie ?? null,
                    'date_fdi' => $fdi->date_fdi ? $fdi->date_fdi->format('Y-m-d\TH:i:s.v\Z') : null,
                    'importateur' => $fdi->importateur ?? null,
                    'fournisseur' => $fdi->fournisseur ?? null,
                    'valeur_caf' => $fdi->valeur_caf ?? null,
                    'valeur_fob_cfa' => $fdi->valeur_fob_cfa ?? null,
                    'valeur_facture_cfa' => $fdi->valeur_facture_cfa ?? null,
                    'devise' => $fdi->nom_devise ?? null,
                    'incoterm' => $fdi->incoterm ?? null,
                    'reglement' => $fdi->reglement ?? null,
                    'banque' => $fdi->banque ?? null,
                ];
            }

            // Construire le message de réponse
            $message = '';
            if ($fdi) {
                $details = [];
                $details[] = "FDI: {$fdi->numero_fdi}";
                if ($fdi->numero_fdi_complet) {
                    $details[] = "Numéro complet: {$fdi->numero_fdi_complet}";
                }
                if ($fdi->importateur) {
                    $details[] = "Importateur: {$fdi->importateur}";
                }
                if ($fdi->valeur_caf) {
                    $details[] = "Valeur CAF: " . number_format((float)$fdi->valeur_caf, 0, ',', ' ') . " XOF";
                }
                $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';
                $message = "FDI liée à la TVF \"{$identifiantTvf}\" récupérée avec succès{$detailsStr}";
            } else {
                $message = "Aucune FDI trouvée pour la TVF \"{$identifiantTvf}\". Vérifiez le numéro FDI ({$banqueTvf->num_fdi}).";
            }

            return [
                'status' => 200,
                'message' => $message,
                'data' => [
                    'tvf' => $tvfData,
                    'fdi' => $fdiData,
                ],
            ];
        });

        return response()->json($payload);
    }

    public function comparaisons(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $cacheKey = "banque-tvf:comparaisons:{$banqueTvf->ulid}";

        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, now()->addMinutes(5), function () use ($banqueTvf) {
            // Construire l'identifiant composite de la TVF (selon EXPLICATION_BANQUE_TVF.md)
            $identifiantsTvf = [];
            if ($banqueTvf->num_fdi) {
                $identifiantsTvf[] = "FDI {$banqueTvf->num_fdi}";
            }
            if ($banqueTvf->ref_ddu) {
                $identifiantsTvf[] = "DDU {$banqueTvf->ref_ddu}";
            }
            if ($banqueTvf->num_dom) {
                $identifiantsTvf[] = "DOM {$banqueTvf->num_dom}";
            }
            $identifiantTvf = !empty($identifiantsTvf) ? implode(' / ', $identifiantsTvf) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

            // Construire le message résumé de la TVF
            $messagePartsTvf = [];
            if ($banqueTvf->statut_ac) {
                $messagePartsTvf[] = "Statut AC: {$banqueTvf->statut_ac}";
            }
            if ($banqueTvf->pays_exp) {
                $messagePartsTvf[] = "Pays: {$banqueTvf->pays_exp}";
            }
            if ($banqueTvf->mont_ac_xof) {
                $messagePartsTvf[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
            }
            $messageResumeTvf = !empty($messagePartsTvf) ? implode(' | ', $messagePartsTvf) : "TVF #{$banqueTvf->id}";

            // Préparer les données de la TVF
            $tvfData = $banqueTvf->toArray();
            $tvfData['identifiant'] = $identifiantTvf;
            $tvfData['message_resume'] = $messageResumeTvf;

            // Charger les comparaisons
            $banqueTvf->load(['comparaison1', 'comparaison2']);
            $comp1 = $banqueTvf->comparaison1;
            $comp2 = $banqueTvf->comparaison2;

            // Préparer les données des comparaisons
            $comparaison1Data = null;
            if ($comp1) {
                $comp1Details = [];
                if ($comp1->num_fdi) {
                    $comp1Details[] = "FDI: {$comp1->num_fdi}";
                }
                if ($comp1->ref_ddu) {
                    $comp1Details[] = "DDU: {$comp1->ref_ddu}";
                }
                if ($comp1->statut_ac) {
                    $comp1Details[] = "Statut AC: {$comp1->statut_ac}";
                }
                if ($comp1->mont_ac_xof) {
                    $comp1Details[] = "Montant AC: " . number_format((float)$comp1->mont_ac_xof, 0, ',', ' ') . " XOF";
                }

                $comparaison1Data = $comp1->toArray();
                $comparaison1Data['identifiant'] = !empty($comp1Details) ? implode(' | ', $comp1Details) : "Comp1 #{$comp1->id}";
            }

            $comparaison2Data = null;
            if ($comp2) {
                $comp2Details = [];
                if ($comp2->num_fdi) {
                    $comp2Details[] = "FDI: {$comp2->num_fdi}";
                }
                if ($comp2->ref_ddu) {
                    $comp2Details[] = "DDU: {$comp2->ref_ddu}";
                }
                if ($comp2->statut_ac) {
                    $comp2Details[] = "Statut AC: {$comp2->statut_ac}";
                }
                if ($comp2->mont_ac_xof) {
                    $comp2Details[] = "Montant AC: " . number_format((float)$comp2->mont_ac_xof, 0, ',', ' ') . " XOF";
                }

                $comparaison2Data = $comp2->toArray();
                $comparaison2Data['identifiant'] = !empty($comp2Details) ? implode(' | ', $comp2Details) : "Comp2 #{$comp2->id}";
            }

            $total = ($comp1 ? 1 : 0) + ($comp2 ? 1 : 0);

            // Construire le message de réponse
            $message = '';
            if ($total > 0) {
                $details = [];
                $details[] = "{$total} comparaison(s) disponible(s)";
                if ($comp1) {
                    $details[] = "Comp1: " . ($comparaison1Data['identifiant'] ?? 'N/A');
                }
                if ($comp2) {
                    $details[] = "Comp2: " . ($comparaison2Data['identifiant'] ?? 'N/A');
                }
                $message = "Comparaisons de la TVF \"{$identifiantTvf}\" récupérées avec succès | " . implode(' | ', $details);
            } else {
                $message = "Aucune comparaison enregistrée pour la TVF \"{$identifiantTvf}\". Les comparaisons sont utilisées pour la synchronisation avec les DDU.";
            }

            return [
                'status' => 200,
                'message' => $message,
                'data' => [
                    'tvf' => $tvfData,
                    'comparaisons' => [
                        'comparaison_1' => $comparaison1Data,
                        'comparaison_2' => $comparaison2Data,
                        'total' => $total,
                        'has_comparaisons' => $total > 0,
                    ],
                ],
            ];
        });

        return response()->json($payload);
    }

    public function validate(string $ulid): JsonResponse
    {
        $banqueTvf = BanqueTvf::where('ulid', $ulid)->firstOrFail();
        $cacheKey = "banque-tvf:validate:{$banqueTvf->ulid}";

        $payload = CacheTagger::tags(['banque-tvf'])->remember($cacheKey, now()->addMinutes(2), function () use ($banqueTvf, $cacheKey) {
            // Construire l'identifiant composite de la TVF (selon EXPLICATION_BANQUE_TVF.md)
            $identifiantsTvf = [];
            if ($banqueTvf->num_fdi) {
                $identifiantsTvf[] = "FDI {$banqueTvf->num_fdi}";
            }
            if ($banqueTvf->ref_ddu) {
                $identifiantsTvf[] = "DDU {$banqueTvf->ref_ddu}";
            }
            if ($banqueTvf->num_dom) {
                $identifiantsTvf[] = "DOM {$banqueTvf->num_dom}";
            }
            $identifiantTvf = !empty($identifiantsTvf) ? implode(' / ', $identifiantsTvf) : ($banqueTvf->ulid ?? "TVF #{$banqueTvf->id}");

            // Vérifications de validation
            $validationChecks = [
                'domiciliation' => [
                    'valid' => !blank($banqueTvf->num_dom) && !blank($banqueTvf->date_dom),
                    'missing' => [],
                ],
                'autorisation_change' => [
                    'valid' => !blank($banqueTvf->statut_ac) && !blank($banqueTvf->num_demande_ac),
                    'missing' => [],
                ],
                'fdi_liee' => [
                    'valid' => !blank($banqueTvf->num_fdi),
                    'missing' => [],
                ],
                'montants' => [
                    'valid' => !blank($banqueTvf->mont_ac_xof) || !blank($banqueTvf->mont_fact_xof),
                    'missing' => [],
                ],
            ];

            // Détecter les champs manquants pour chaque vérification
            if (!$validationChecks['domiciliation']['valid']) {
                $validationChecks['domiciliation']['missing'] = collect(['num_dom', 'date_dom'])
                    ->filter(fn ($field) => blank($banqueTvf->{$field}))
                    ->values()
                    ->all();
            }

            if (!$validationChecks['autorisation_change']['valid']) {
                $validationChecks['autorisation_change']['missing'] = collect(['statut_ac', 'num_demande_ac'])
                    ->filter(fn ($field) => blank($banqueTvf->{$field}))
                    ->values()
                    ->all();
            }

            if (!$validationChecks['fdi_liee']['valid']) {
                $validationChecks['fdi_liee']['missing'] = ['num_fdi'];
            }

            if (!$validationChecks['montants']['valid']) {
                $validationChecks['montants']['missing'] = collect(['mont_ac_xof', 'mont_fact_xof'])
                    ->filter(fn ($field) => blank($banqueTvf->{$field}))
                    ->values()
                    ->all();
            }

            // Calculer le taux de complétude
            $totalChecks = count($validationChecks);
            $validChecks = collect($validationChecks)->filter(fn ($check) => $check['valid'])->count();
            $completionRate = $totalChecks > 0 ? round(($validChecks / $totalChecks) * 100, 2) : 0;
            $isValid = $completionRate === 100.0;

            // Vérifier si la FDI existe
            $fdi = $banqueTvf->fdi;
            $fdiExists = $fdi !== null;

            // Préparer les données de la TVF
            $tvfData = $banqueTvf->toArray();
            $tvfData['identifiant'] = $identifiantTvf;
            
            // Construire le message résumé de la TVF
            $messagePartsTvf = [];
            if ($banqueTvf->statut_ac) {
                $messagePartsTvf[] = "Statut AC: {$banqueTvf->statut_ac}";
            }
            if ($banqueTvf->pays_exp) {
                $messagePartsTvf[] = "Pays: {$banqueTvf->pays_exp}";
            }
            if ($banqueTvf->mont_ac_xof) {
                $messagePartsTvf[] = "Montant AC: " . number_format((float)$banqueTvf->mont_ac_xof, 0, ',', ' ') . " XOF";
            }
            $tvfData['message_resume'] = !empty($messagePartsTvf) ? implode(' | ', $messagePartsTvf) : "TVF #{$banqueTvf->id}";

            // Construire le message de validation
            $message = '';
            if ($isValid) {
                $details = [];
                if ($banqueTvf->num_dom) {
                    $details[] = "DOM: {$banqueTvf->num_dom}";
                }
                if ($banqueTvf->statut_ac) {
                    $details[] = "Statut AC: {$banqueTvf->statut_ac}";
                }
                if ($fdiExists) {
                    $details[] = "FDI liée: {$banqueTvf->num_fdi}";
                }
                $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : "";
                $message = "TVF \"{$identifiantTvf}\" validée avec succès (100% complète){$detailsStr}. Prête pour traitement.";
            } else {
                $missingFields = collect($validationChecks)
                    ->flatMap(fn ($check, $key) => $check['missing'] ? ["{$key}: " . implode(', ', $check['missing'])] : [])
                    ->values()
                    ->all();
                $missingStr = !empty($missingFields) ? " | Champs manquants: " . implode(' | ', $missingFields) : "";
                $message = "TVF \"{$identifiantTvf}\" incomplète ({$completionRate}% complète){$missingStr}. Vérifiez les éléments manquants.";
            }

            // Log the validation
            $validationDetails = $isValid 
                ? "Validation complète ({$completionRate}%)" 
                : "Validation incomplète ({$completionRate}%) - éléments manquants";
            
            AuditService::log('validate', "Validation TVF \"{$identifiantTvf}\" : {$validationDetails}", 'BanqueTvf', $banqueTvf->id, null, [
                'is_valid' => $isValid,
                'completion_rate' => $completionRate,
                'validation_checks' => $validationChecks,
                'fdi_exists' => $fdiExists,
            ]);

            // Invalider le cache après validation (pour forcer le rafraîchissement)
            CacheTagger::tags(['banque-tvf'])->forget($cacheKey);

            return [
                'status' => 200,
                'message' => $message,
                'data' => [
                    'tvf' => $tvfData,
                    'validation' => [
                        'valid' => $isValid,
                        'complete' => $isValid,
                        'completion_rate' => $completionRate,
                        'checks' => $validationChecks,
                        'fdi_exists' => $fdiExists,
                        'fdi' => $fdi ? [
                            'ulid' => $fdi->ulid,
                            'numero_fdi' => $fdi->numero_fdi,
                            'numero_fdi_complet' => $fdi->numero_fdi_complet ?? null,
                            'identifiant' => $fdi->identifiant ?? $fdi->numero_fdi,
                        ] : null,
                    ],
                ],
            ];
        });

        return response()->json($payload);
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




