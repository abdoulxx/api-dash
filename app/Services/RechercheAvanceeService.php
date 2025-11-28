<?php

namespace App\Services;

use App\Models\Banque;
use App\Models\BanqueSad;
use App\Models\BanqueTvf;
use App\Models\BonProvisoireArticle;
use App\Models\BonProvisoireSg;
use App\Models\DeclarationArticle;
use App\Models\DeclarationSg;
use App\Models\FcvrArticle;
use App\Models\FcvrSg;
use App\Models\FdiArticle;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Models\ManifesteTc;
use App\Models\ManifesteTt;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RechercheAvanceeService
{
    /**
     * Options de recherche disponibles
     * Deux groupes : Option 1 (entités) et Option 2 (critères)
     */
    public function getSearchOptions(): array
    {
        return [
            'option_1' => [
            [
                'value' => 'FDI',
                'label' => 'FDI',
                'fields' => ['numero_fdi', 'annee', 'bureau', 'importateur'],
                'description' => 'Recherche par numéro FDI, année, bureau ou importateur'
            ],
                [
                    'value' => 'RFCV',
                    'label' => 'RFCV',
                    'fields' => ['num_rfcv', 'num_bl', 'num_voyage'],
                    'description' => 'Recherche par numéro RFCV, BL ou voyage'
                ],
                [
                    'value' => 'Declaration',
                    'label' => 'Déclaration',
                    'fields' => ['declaration', 'num_manifeste', 'num_fdi'],
                    'description' => 'Recherche par numéro de déclaration, manifeste ou FDI'
                ],
                [
                    'value' => 'ManifesteTT',
                    'label' => 'Manifeste TT',
                    'fields' => ['num_titre_transport', 'num_voy_ds', 'num_manifeste'],
                    'description' => 'Recherche par titre de transport (BL/LTA/CMR)'
                ],
                [
                    'value' => 'AC',
                    'label' => 'AC (Autorisation de Crédit)',
                    'fields' => ['num_ddu', 'ref_ddu', 'num_dvt'],
                    'description' => 'Recherche par numéro AC, DDU ou DVT'
                ]
            ],
            'option_2' => [
            [
                'value' => 'Importateur',
                'label' => 'Importateur',
                    'fields' => ['importateur', 'cc', 'nom_importateur'],
                'description' => 'Recherche par nom d\'importateur ou code importateur'
            ],
            [
                'value' => 'Manifeste',
                'label' => 'Manifeste',
                    'fields' => ['num_manifeste', 'numero_manifeste_complet', 'num_voyage', 'nom_moyen_transport'],
                    'description' => 'Recherche par numéro de manifeste complet, voyage ou moyen de transport'
                ],
                [
                    'value' => 'Annee',
                    'label' => 'Année',
                    'fields' => ['annee', 'annee_manifeste', 'annee_fdi', 'annee_declaration'],
                    'description' => 'Recherche par année (manifeste, FDI, déclaration)'
                ],
                [
                    'value' => 'Declarant',
                    'label' => 'Déclarant',
                    'fields' => ['declarant', 'nom_declarant', 'code_declarant'],
                    'description' => 'Recherche par nom ou code de déclarant'
                ],
                [
                    'value' => 'BureauPort',
                    'label' => 'Bureau du Port',
                    'fields' => ['code_bureau', 'bureau', 'nom_bureau', 'code_port'],
                    'description' => 'Recherche par code ou nom de bureau douanier/port'
                ],
                [
                    'value' => 'PaysExportateur',
                    'label' => 'Pays Exportateur',
                    'fields' => ['pays_exportateur', 'pays_fournisseur', 'code_pays_export', 'nom_pays_export'],
                    'description' => 'Recherche par pays exportateur ou fournisseur'
                ]
            ]
        ];
    }

    /**
     * Recherche avancée principale
     */
    public function searchAdvanced(string $option, string $valeur, array $filters = [], int $perPage = 15, int $page = 1, bool $logAudit = true): array
    {
        $cacheKey = sprintf('recherche_avancee:%s:%s:%s:%d:%d', 
            $option, 
            md5($valeur), 
            md5(json_encode($filters)), 
            $perPage, 
            $page
        );

        $payload = CacheTagger::tags(['recherche', 'recherche-avancee'])->remember($cacheKey, 300, function () use ($option, $valeur, $filters, $perPage, $page) {
            $results = [
                'fdi_general' => [],
                'fdi_article' => [],
                'fcvr_general' => [],
                'fcvr_article' => [],
                'manifeste_general' => [],
                'manifeste_tt' => [],
                'manifeste_tc' => [],
                'declaration_general' => [],
                'declaration_article' => [],
                'banque_sad' => [],
                'banque_tvf' => [],
                'bon_provisoire_general' => [],
                'bon_provisoire_article' => [],
            ];

            $flowDiagram = ['nodes' => [], 'edges' => []];

            switch (strtolower($option)) {
                // Option 1 : Recherche par entités
                case 'fdi':
                    $results = $this->searchByFdi($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromFdi($valeur, $filters);
                    break;
                case 'rfcv':
                    $results = $this->searchByRfcv($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromRfcv($valeur, $filters);
                    break;
                case 'declaration':
                    $results = $this->searchByDeclaration($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromDeclaration($valeur, $filters);
                    break;
                case 'manifestett':
                    $results = $this->searchByManifesteTt($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromManifesteTt($valeur, $filters);
                    break;
                case 'ac':
                    $results = $this->searchByAc($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromAc($valeur, $filters);
                    break;
                // Option 2 : Recherche par critères
                case 'importateur':
                    $results = $this->searchByImportateur($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromImportateur($valeur, $filters);
                    break;
                case 'manifeste':
                    // Recherche par Manifeste (Option 2) - retourne les résultats enrichis avec toutes les relations
                    $results = $this->searchByManifesteEnriched($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromManifeste($valeur, $filters);
                    break;
                case 'annee':
                    $results = $this->searchByAnnee($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromAnnee($valeur, $filters);
                    break;
                case 'declarant':
                    $results = $this->searchByDeclarant($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromDeclarant($valeur, $filters);
                    break;
                case 'bureauport':
                    $results = $this->searchByBureauPort($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromBureauPort($valeur, $filters);
                    break;
                case 'paysexportateur':
                    $results = $this->searchByPaysExportateur($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromPaysExportateur($valeur, $filters);
                    break;
            }

            return [
                'results' => $results,
                'flow_diagram' => $flowDiagram,
            ];
        });

        // Log audit si demandé
        if ($logAudit) {
            $filtersText = !empty($filters) ? ' avec filtres: ' . json_encode($filters) : '';
            AuditService::log('search', "Recherche avancée effectuée: option={$option}, valeur={$valeur}{$filtersText}", 'RechercheAvancee', null, null, [
                'option' => $option,
                'valeur' => $valeur,
                'filters' => $filters,
                'per_page' => $perPage,
                'page' => $page,
            ]);
        }

        return $payload;
    }

    /**
     * Recherche par FDI
     */
    private function searchByFdi(string $valeur, array $filters, int $perPage, int $page): array
    {
        $query = FdiSg::query()
            ->where(function ($q) use ($valeur) {
                $q->where('numero_fdi', 'like', "%{$valeur}%")
                  ->orWhere('importateur', 'like', "%{$valeur}%")
                  ->orWhere('cc', 'like', "%{$valeur}%");
            });

        $this->applyCommonFilters($query, $filters);

        $fdiGeneral = $query->paginate($perPage, ['*'], 'page', $page);

        // Récupérer les articles des FDI trouvées
        $fdiIds = [];
        if ($fdiGeneral) {
            $fdiIds = $fdiGeneral->pluck('numero_fdi')->filter()->toArray();
        }
        $fdiArticles = null;
        if (!empty($fdiIds)) {
            $fdiArticles = FdiArticle::whereIn('numero_fdi', $fdiIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Récupérer les RFCV liées
        $fcvrGeneral = null;
        if (!empty($fdiIds)) {
            $fcvrGeneral = FcvrSg::whereIn('num_fdi', $fdiIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        $fcvrInstanceIds = [];
        if ($fcvrGeneral) {
            $fcvrInstanceIds = $fcvrGeneral->pluck('instanceid')->filter()->toArray();
        }
        $fcvrArticles = null;
        if (!empty($fcvrInstanceIds)) {
            $fcvrArticles = FcvrArticle::whereIn('instanceid', $fcvrInstanceIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Récupérer les manifestes liés via RFCV
        $numBls = [];
        if ($fcvrGeneral) {
            $numBls = $fcvrGeneral->pluck('num_bl')->filter()->toArray();
        }
        $manifesteGeneral = null;
        $manifesteTt = null;
        $manifesteTc = null;
        
        if (!empty($numBls)) {
            $manifesteTtQuery = ManifesteTt::whereIn('num_titre_transport', $numBls)->get();
            $numManifestes = $manifesteTtQuery->pluck('num_manifeste')->filter()->unique()->toArray();
            
            if (!empty($numManifestes)) {
                $manifesteGeneral = ManifesteSg::whereIn('num_manifeste', $numManifestes)
                    ->paginate($perPage, ['*'], 'page', $page);
                $manifesteTt = $manifesteTtQuery->slice(($page - 1) * $perPage, $perPage)->values();
                $manifesteTc = ManifesteTc::withoutGlobalScopes()
                    ->whereIn('num_manifeste', $numManifestes)
                    ->paginate($perPage, ['*'], 'page', $page);
            }
        }

        return [
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => $this->extractItems($fdiArticles),
            'fcvr_general' => $this->extractItems($fcvrGeneral),
            'fcvr_article' => $this->extractItems($fcvrArticles),
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => $this->extractItems($manifesteTt),
            'manifeste_tc' => $this->extractItems($manifesteTc),
        ];
    }

    /**
     * Recherche par Importateur
     */
    private function searchByImportateur(string $valeur, array $filters, int $perPage, int $page): array
    {
        $query = FdiSg::query()
            ->where(function ($q) use ($valeur) {
                $q->where('importateur', 'like', "%{$valeur}%")
                  ->orWhere('cc', 'like', "%{$valeur}%");
            });

        $this->applyCommonFilters($query, $filters);

        $fdiGeneral = $query->paginate($perPage, ['*'], 'page', $page);

        // Même logique que searchByFdi pour les autres onglets
        return $this->getRelatedDataFromFdi($fdiGeneral, $perPage, $page);
    }

    /**
     * Recherche par Manifeste
     */
    private function searchByManifeste(string $valeur, array $filters, int $perPage, int $page): array
    {
        $query = ManifesteSg::query()
            ->where(function ($q) use ($valeur) {
                $q->where('num_manifeste', 'like', "%{$valeur}%")
                  ->orWhere('num_voyage', 'like', "%{$valeur}%")
                  ->orWhere('nom_moyen_transport', 'like', "%{$valeur}%")
                  ->orWhere('nom_transport', 'like', "%{$valeur}%")
                  // Recherche aussi dans le numero_manifeste_complet construit
                  ->orWhereRaw("CONCAT(code_bureau, ' ', annee_manifeste, ' ', COALESCE(num_man_sydam, instance_id)) LIKE ?", ["%{$valeur}%"]);
                
                // Si la valeur correspond au format "CODE ANNEE NUMERO", recherche exacte
                if (preg_match('/^([A-Z0-9]+)\s+(\d{4})\s+(\d+)$/', trim($valeur), $matches)) {
                    $codeBureau = $matches[1];
                    $annee = (int) $matches[2];
                    $numeroSeq = $matches[3];
                    
                    $q->orWhere(function ($subQ) use ($codeBureau, $annee, $numeroSeq) {
                        $subQ->where('code_bureau', $codeBureau)
                             ->where('annee_manifeste', $annee)
                             ->where(function ($seqQ) use ($numeroSeq) {
                                 $seqQ->where('num_man_sydam', $numeroSeq)
                                      ->orWhere('instance_id', $numeroSeq);
                             });
                    });
                }
            });

        $this->applyCommonFilters($query, $filters);

        $manifesteGeneral = $query->paginate($perPage, ['*'], 'page', $page);

        $numManifestes = [];
        if ($manifesteGeneral) {
            $numManifestes = $manifesteGeneral->pluck('num_manifeste')->filter()->unique()->toArray();
        }

        $manifesteTt = null;
        $manifesteTc = null;
        if (!empty($numManifestes)) {
            $manifesteTt = ManifesteTt::whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
            $manifesteTc = ManifesteTc::withoutGlobalScopes()
                ->whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Récupérer les déclarations liées
        $declarations = DeclarationSg::whereIn('num_manifeste', $numManifestes)
            ->paginate($perPage, ['*'], 'page', $page);

        // Récupérer les RFCV via BL
        $numBls = [];
        if ($manifesteTt) {
            $numBls = $manifesteTt->pluck('num_titre_transport')->filter()->toArray();
        }
        $fcvrGeneral = null;
        if (!empty($numBls)) {
            $fcvrGeneral = FcvrSg::whereIn('num_bl', $numBls)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Récupérer les FDI via RFCV
        $numFdis = [];
        if ($fcvrGeneral) {
            $numFdis = $fcvrGeneral->pluck('num_fdi')->filter()->toArray();
        }
        $fdiGeneral = null;
        if (!empty($numFdis)) {
            $fdiGeneral = FdiSg::whereIn('numero_fdi', $numFdis)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => [],
            'fcvr_general' => $this->extractItems($fcvrGeneral),
            'fcvr_article' => [],
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => $this->extractItems($manifesteTt),
            'manifeste_tc' => $this->extractItems($manifesteTc),
        ];
    }

    /**
     * Recherche enrichie par Manifeste (Option 2)
     * Retourne toutes les entités liées selon la logique s360_analyse
     * Relations suivies :
     * - MANIFESTE_SG → MANIFESTE_TT (via NUM_MANIFESTE)
     * - MANIFESTE_TT → FCVR_SG (via NUM_VOY_DS = NUM_VOYAGE et NUM_TITRE_TRANSPORT = NUM_BL)
     * - FCVR_SG → FDI_SG (via NUM_FDI = NUMERO_FDI et DATE_FDI = DATE_FDI)
     * - MANIFESTE_TT → DECLARATION_SG (via NUM_MANIFESTE et NUM_TITRE_TRANSPORT = NUM_BL)
     * - DECLARATION_SG → DECLARATION_ARTICLE (via DECLARATION)
     * - DECLARATION_SG → BANQUE_SAD (via REF_DDU)
     * - DECLARATION_SG → BON_PROVISOIRE_ARTICLE (via NUM_DECLARATION)
     * - FDI_SG → BANQUE_TVF (via NUM_FDI et DATE_FDI)
     * - MANIFESTE_SG → MANIFESTE_TC (via NUM_MANIFESTE)
     */
    private function searchByManifesteEnriched(string $valeur, array $filters, int $perPage, int $page): array
    {
        // 1. Rechercher les manifestes (MANIFESTE_SG)
        $query = ManifesteSg::query()
            ->where(function ($q) use ($valeur) {
                $q->where('num_manifeste', 'like', "%{$valeur}%")
                  ->orWhere('num_voyage', 'like', "%{$valeur}%")
                  ->orWhere('nom_moyen_transport', 'like', "%{$valeur}%")
                  ->orWhere('nom_transport', 'like', "%{$valeur}%")
                  ->orWhereRaw("CONCAT(code_bureau, ' ', annee_manifeste, ' ', COALESCE(num_man_sydam, instance_id)) LIKE ?", ["%{$valeur}%"]);
                
                // Si la valeur correspond au format "CODE ANNEE NUMERO", recherche exacte
                if (preg_match('/^([A-Z0-9]+)\s+(\d{4})\s+(\d+)$/', trim($valeur), $matches)) {
                    $codeBureau = $matches[1];
                    $annee = (int) $matches[2];
                    $numeroSeq = $matches[3];
                    
                    $q->orWhere(function ($subQ) use ($codeBureau, $annee, $numeroSeq) {
                        $subQ->where('code_bureau', $codeBureau)
                             ->where('annee_manifeste', $annee)
                             ->where(function ($seqQ) use ($numeroSeq) {
                                 $seqQ->where('num_man_sydam', $numeroSeq)
                                      ->orWhere('instance_id', $numeroSeq);
                             });
                    });
                }
            });

        $this->applyCommonFilters($query, $filters);
        $manifesteGeneral = $query->paginate($perPage, ['*'], 'page', $page);

        $numManifestes = [];
        if ($manifesteGeneral) {
            $numManifestes = $manifesteGeneral->pluck('num_manifeste')->filter()->unique()->toArray();
        }

        // 2. Récupérer les titres de transport (MANIFESTE_TT)
        $manifesteTt = null;
        $numVoyages = [];
        $numBls = [];
        if (!empty($numManifestes)) {
            $manifesteTt = ManifesteTt::whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
            
            if ($manifesteTt) {
                $numVoyages = $manifesteTt->pluck('num_voy_ds')->filter()->unique()->toArray();
                $numBls = $manifesteTt->pluck('num_titre_transport')->filter()->unique()->toArray();
            }
        }

        // 3. Récupérer les conteneurs (MANIFESTE_TC)
        $manifesteTc = null;
        if (!empty($numManifestes)) {
            $manifesteTc = ManifesteTc::withoutGlobalScopes()
                ->whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // 4. Récupérer les FCVR (via NUM_VOY_DS = NUM_VOYAGE et NUM_TITRE_TRANSPORT = NUM_BL) - selon s360_analyse
        $fcvrGeneral = null;
        $fcvrArticles = null;
        if ($manifesteTt && $manifesteTt->count() > 0) {
            // Relation selon s360_analyse : MANIFESTE_TT.NUM_VOY_DS = FCVR_SG.NUM_VOYAGE 
            // ET MANIFESTE_TT.NUM_TITRE_TRANSPORT = FCVR_SG.NUM_BL
            // Construire les paires (voyage, bl) depuis MANIFESTE_TT
            $pairs = [];
            foreach ($manifesteTt->items() as $tt) {
                if ($tt->num_voy_ds && $tt->num_titre_transport) {
                    $pairs[] = [
                        'voyage' => $tt->num_voy_ds,
                        'bl' => $tt->num_titre_transport
                    ];
                }
            }
            
            if (!empty($pairs)) {
                // Recherche FCVR qui correspondent à au moins une paire (voyage, bl)
                $fcvrGeneral = FcvrSg::where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        $q->orWhere(function ($subQ) use ($pair) {
                            $subQ->where('num_voyage', $pair['voyage'])
                                 ->where('num_bl', $pair['bl']);
                        });
                    }
                })->paginate($perPage, ['*'], 'page', $page);
                
                if ($fcvrGeneral) {
                    $fcvrInstanceIds = $fcvrGeneral->pluck('instanceid')->filter()->toArray();
                    if (!empty($fcvrInstanceIds)) {
                        $fcvrArticles = FcvrArticle::whereIn('instanceid', $fcvrInstanceIds)
                            ->paginate($perPage, ['*'], 'page', $page);
                    }
                }
            }
        }

        // 5. Récupérer les FDI (via FCVR → NUM_FDI) - selon s360_analyse
        $fdiGeneral = null;
        $fdiArticles = null;
        $numFdis = [];
        $dateFdis = [];
        if ($fcvrGeneral) {
            $numFdis = $fcvrGeneral->pluck('num_fdi')->filter()->unique()->toArray();
            $dateFdis = $fcvrGeneral->pluck('date_fdi')->filter()->unique()->toArray();
        }
        
        if (!empty($numFdis) && !empty($dateFdis)) {
            $fdiGeneral = FdiSg::whereIn('numero_fdi', $numFdis)
                ->whereIn('date_fdi', $dateFdis)
                ->paginate($perPage, ['*'], 'page', $page);
            
            if ($fdiGeneral) {
                $fdiNumeroFdis = $fdiGeneral->pluck('numero_fdi')->filter()->toArray();
                if (!empty($fdiNumeroFdis)) {
                    $fdiArticles = FdiArticle::whereIn('numero_fdi', $fdiNumeroFdis)
                        ->paginate($perPage, ['*'], 'page', $page);
                }
            }
        }

        // 6. Récupérer les Déclarations (via NUM_MANIFESTE et NUM_BL) - selon s360_analyse
        $declarations = null;
        $declarationArticles = null;
        $declarationIds = [];
        if (!empty($numManifestes) && !empty($numBls)) {
            $declarations = DeclarationSg::whereIn('num_manifeste', $numManifestes)
                ->whereIn('num_bl', $numBls)
                ->paginate($perPage, ['*'], 'page', $page);
            
            if ($declarations) {
                $declarationIds = $declarations->pluck('declaration')->filter()->unique()->toArray();
                if (!empty($declarationIds)) {
                    $declarationArticles = DeclarationArticle::whereIn('declaration', $declarationIds)
                        ->paginate($perPage, ['*'], 'page', $page);
                }
            }
        }

        // 7. Récupérer les Banque SAD (via DECLARATION → REF_DDU) - selon s360_analyse
        $banqueSad = null;
        if (!empty($declarationIds)) {
            $banqueSad = BanqueSad::whereIn('ref_ddu', $declarationIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // 8. Récupérer les Banque TVF (via FDI → NUM_FDI et DATE_FDI) - selon s360_analyse
        $banqueTvf = null;
        if (!empty($numFdis) && !empty($dateFdis)) {
            $banqueTvf = BanqueTvf::whereIn('num_fdi', $numFdis)
                ->whereIn('date_fdi', $dateFdis)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // 9. Récupérer les Bons Provisoires (via DECLARATION → NUM_DECLARATION) - selon s360_analyse
        $bonProvisoireArticles = null;
        $bonProvisoireIds = [];
        if (!empty($declarationIds)) {
            $bonProvisoireArticles = BonProvisoireArticle::whereIn('num_declaration', $declarationIds)
                ->paginate($perPage, ['*'], 'page', $page);
            
            if ($bonProvisoireArticles) {
                $bonProvisoireIds = $bonProvisoireArticles->pluck('numero_bon_provisoire')->filter()->unique()->toArray();
            }
        }

        $bonProvisoireGeneral = null;
        if (!empty($bonProvisoireIds)) {
            $bonProvisoireGeneral = BonProvisoireSg::whereIn('numero_bon_provisoire', $bonProvisoireIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => $this->extractItems($manifesteTt),
            'manifeste_tc' => $this->extractItems($manifesteTc),
            'fcvr_general' => $this->extractItems($fcvrGeneral),
            'fcvr_article' => $this->extractItems($fcvrArticles),
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => $this->extractItems($fdiArticles),
            'declaration_general' => $this->extractItems($declarations),
            'declaration_article' => $this->extractItems($declarationArticles),
            'banque_sad' => $this->extractItems($banqueSad),
            'banque_tvf' => $this->extractItems($banqueTvf),
            'bon_provisoire_general' => $this->extractItems($bonProvisoireGeneral),
            'bon_provisoire_article' => $this->extractItems($bonProvisoireArticles),
        ];
    }

    /**
     * Recherche par RFCV
     */
    private function searchByRfcv(string $valeur, array $filters, int $perPage, int $page): array
    {
        $query = FcvrSg::query()
            ->where(function ($q) use ($valeur) {
                $q->where('num_rfcv', 'like', "%{$valeur}%")
                  ->orWhere('num_bl', 'like', "%{$valeur}%")
                  ->orWhere('num_voyage', 'like', "%{$valeur}%");
            });

        $this->applyCommonFilters($query, $filters);

        $fcvrGeneral = $query->paginate($perPage, ['*'], 'page', $page);

        $instanceIds = [];
        if ($fcvrGeneral) {
            $instanceIds = $fcvrGeneral->pluck('instanceid')->filter()->toArray();
        }
        $fcvrArticles = null;
        if (!empty($instanceIds)) {
            $fcvrArticles = FcvrArticle::whereIn('instanceid', $instanceIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Récupérer les FDI liées
        $numFdis = [];
        if ($fcvrGeneral) {
            $numFdis = $fcvrGeneral->pluck('num_fdi')->filter()->toArray();
        }
        $fdiGeneral = null;
        $fdiArticles = null;
        if (!empty($numFdis)) {
            $fdiGeneral = FdiSg::whereIn('numero_fdi', $numFdis)
                ->paginate($perPage, ['*'], 'page', $page);
            
            $fdiArticles = FdiArticle::whereIn('numero_fdi', $numFdis)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Récupérer les manifestes via BL
        $numBls = [];
        if ($fcvrGeneral) {
            $numBls = $fcvrGeneral->pluck('num_bl')->filter()->toArray();
        }
        $manifesteGeneral = null;
        $manifesteTt = null;
        $manifesteTc = null;
        
        if (!empty($numBls)) {
            $manifesteTtQuery = ManifesteTt::whereIn('num_titre_transport', $numBls)->get();
            $numManifestes = $manifesteTtQuery->pluck('num_manifeste')->filter()->unique()->toArray();
            
            if (!empty($numManifestes)) {
                $manifesteGeneral = ManifesteSg::whereIn('num_manifeste', $numManifestes)
                    ->paginate($perPage, ['*'], 'page', $page);
                $manifesteTt = $manifesteTtQuery->slice(($page - 1) * $perPage, $perPage)->values();
                $manifesteTc = ManifesteTc::withoutGlobalScopes()
                    ->whereIn('num_manifeste', $numManifestes)
                    ->paginate($perPage, ['*'], 'page', $page);
            }
        }

        return [
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => $this->extractItems($fdiArticles),
            'fcvr_general' => $this->extractItems($fcvrGeneral),
            'fcvr_article' => $this->extractItems($fcvrArticles),
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => $this->extractItems($manifesteTt),
            'manifeste_tc' => $this->extractItems($manifesteTc),
        ];
    }

    /**
     * Recherche par Déclaration
     */
    private function searchByDeclaration(string $valeur, array $filters, int $perPage, int $page): array
    {
        $query = DeclarationSg::query()
            ->where(function ($q) use ($valeur) {
                $q->where('declaration', 'like', "%{$valeur}%")
                  ->orWhere('num_manifeste', 'like', "%{$valeur}%")
                  ->orWhere('num_fdi', 'like', "%{$valeur}%");
            });

        $this->applyCommonFilters($query, $filters);

        $declarations = $query->paginate($perPage, ['*'], 'page', $page);

        // Récupérer les manifestes liés
        $numManifestes = [];
        if ($declarations) {
            $numManifestes = $declarations->pluck('num_manifeste')->filter()->unique()->toArray();
        }
        $manifesteGeneral = null;
        $manifesteTt = null;
        $manifesteTc = null;
        
        if (!empty($numManifestes)) {
            $manifesteGeneral = ManifesteSg::whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
            $manifesteTt = ManifesteTt::whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
            $manifesteTc = ManifesteTc::withoutGlobalScopes()
                ->whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        // Récupérer les FDI liées
        $numFdis = [];
        if ($declarations) {
            $numFdis = $declarations->pluck('num_fdi')->filter()->toArray();
        }
        $fdiGeneral = null;
        if (!empty($numFdis)) {
            $fdiGeneral = FdiSg::whereIn('numero_fdi', $numFdis)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => [],
            'fcvr_general' => [],
            'fcvr_article' => [],
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => $this->extractItems($manifesteTt),
            'manifeste_tc' => $this->extractItems($manifesteTc),
        ];
    }

    /**
     * Calcule la couleur d'un node selon l'existence du document et les délais
     * 
     * Règles :
     * - Vert : Le document existe
     * - Rouge : Le document n'existe pas ET le délai est dépassé
     * - Gris : Le document n'existe pas MAIS on est toujours dans le délai
     * 
     * @param string $type Type de document (FDI, FCVR, Declaration, Manifeste, AC, etc.)
     * @param bool $exists Si le document existe
     * @param \DateTime|null $referenceDate Date de référence pour calculer le délai (ex: date_arrivee_navire pour manifeste)
     * @param int|null $delayDays Délai légal en jours (null = utiliser les délais par défaut)
     * @return string 'green', 'red', ou 'grey'
     */
    private function calculateNodeColor(string $type, bool $exists, ?\DateTime $referenceDate = null, ?int $delayDays = null): string
    {
        // Si le document existe, toujours vert
        if ($exists) {
            return 'green';
        }

        // Si pas de date de référence, on ne peut pas calculer → gris par défaut
        if (!$referenceDate) {
            return 'grey';
        }

        // Délais légaux par défaut (en jours) selon s360_analyse
        $defaultDelays = [
            'Manifeste' => 0,      // Le manifeste est le point de départ
            'FCVR' => 30,          // FCVR doit être créé dans les 30 jours après arrivée manifeste
            'RFCV' => 30,          // Alias de FCVR
            'FDI' => 60,           // FDI doit être créé dans les 60 jours après arrivée manifeste
            'Declaration' => 90,    // Déclaration doit être créée dans les 90 jours après arrivée manifeste
            'AC' => 45,            // AC (Autorisation de Change) doit être créée dans les 45 jours après FDI
            'BanqueTvf' => 45,     // Banque TVF doit être créée dans les 45 jours après FDI
            'BanqueSad' => 90,     // Banque SAD doit être créée dans les 90 jours après déclaration
            'BonProvisoire' => 30, // Bon Provisoire doit être créé dans les 30 jours après déclaration
        ];

        // Utiliser le délai fourni ou le délai par défaut
        $delay = $delayDays ?? $defaultDelays[$type] ?? 30;
        
        // Calculer le nombre de jours écoulés depuis la date de référence
        $now = new \DateTime();
        $daysElapsed = $now->diff($referenceDate)->days;
        
        // Si le délai est dépassé → rouge
        if ($daysElapsed > $delay) {
            return 'red';
        }
        
        // Sinon, on est encore dans le délai → gris
        return 'grey';
    }

    /**
     * Construit le diagramme de flux à partir d'une recherche FDI
     * Relations selon s360_analyse :
     * - FDI → FCVR (via NUM_FDI et DATE_FDI)
     * - FCVR → Manifeste (via NUM_VOYAGE et NUM_BL)
     * - FCVR → Déclaration (via NUM_DECLARATION)
     * - Manifeste → Déclaration (via NUM_MANIFESTE et NUM_BL)
     * - FDI → Banque TVF (via NUM_FDI et DATE_FDI)
     * - Déclaration → Banque SAD (via REF_DDU)
     * - Déclaration → Bon Provisoire (via NUM_DECLARATION)
     */
    private function buildFlowDiagramFromFdi(string $valeur, array $filters): array
    {
        // Rechercher la FDI par numero_fdi ou numero_fdi_complet
        $driver = DB::getDriverName();
        $fdi = FdiSg::where(function ($q) use ($valeur, $driver) {
            $q->where('numero_fdi', 'like', "%{$valeur}%")
              ->orWhere('importateur', 'like', "%{$valeur}%");
            
            // Recherche dans le numero_fdi_complet construit (convertir les entiers en text pour PostgreSQL)
            if ($driver === 'pgsql') {
                $q->orWhereRaw("CONCAT(COALESCE(CAST(annee AS TEXT), ''), COALESCE(bureau, ''), COALESCE(serie_fdi, ''), COALESCE(CAST(numero_serie AS TEXT), '')) LIKE ?", ["%{$valeur}%"]);
            } else {
                $q->orWhereRaw("CONCAT(COALESCE(annee, ''), COALESCE(bureau, ''), COALESCE(serie_fdi, ''), COALESCE(numero_serie, '')) LIKE ?", ["%{$valeur}%"]);
            }
            
            // Si la valeur correspond au format "ANNEEBUREAUSERIENUMERO", recherche exacte
            if (preg_match('/^(\d{4})([A-Z0-9]+)([A-Z])(\d+)$/', trim($valeur), $matches)) {
                $annee = (int) $matches[1];
                $bureau = $matches[2];
                $serie = $matches[3];
                $numero = $matches[4];
                
                $q->orWhere(function ($subQ) use ($annee, $bureau, $serie, $numero) {
                    $subQ->where('annee', $annee)
                         ->where('bureau', $bureau)
                         ->where('serie_fdi', $serie)
                         ->where('numero_serie', $numero);
                });
            }
        })->first();

        if (!$fdi) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];
        $processedNodes = []; // Pour éviter les doublons

        // Date de référence pour calculer les délais (date FDI)
        $fdiReferenceDate = $fdi->date_fdi ?? new \DateTime();

        // Node FDI (existe → vert)
        $fdiNodeId = "fdi_{$fdi->ulid}";
        $nodes[] = [
            'id' => $fdiNodeId,
            'type' => 'FDI',
            'label' => $fdi->numero_fdi_complet ?? $fdi->numero_fdi,
            'count' => 1,
            'ulid' => $fdi->ulid,
            'color' => $this->calculateNodeColor('FDI', true, $fdiReferenceDate)
        ];
        $processedNodes['fdi'] = $fdiNodeId;

        // 1. FCVR liées (via NUM_FDI et DATE_FDI) - selon s360_analyse
        $fcvrList = FcvrSg::where('num_fdi', $fdi->numero_fdi)
            ->where('date_fdi', $fdi->date_fdi)
            ->get();
        
        $fcvrExists = $fcvrList->isNotEmpty();
        $fcvrReferenceDate = $fdiReferenceDate; // Date de référence = date FDI
        
        if ($fcvrExists) {
            foreach ($fcvrList as $fcvr) {
                $fcvrNodeId = "fcvr_{$fcvr->ulid}";
                if (!in_array($fcvrNodeId, $processedNodes)) {
            $nodes[] = [
                        'id' => $fcvrNodeId,
                'type' => 'RFCV',
                        'label' => $fcvr->num_rfcv ?? 'FCVR',
                'count' => 1,
                'ulid' => $fcvr->ulid,
                        'color' => $this->calculateNodeColor('FCVR', true, $fcvrReferenceDate)
            ];
                    $processedNodes['fcvr_' . $fcvr->ulid] = $fcvrNodeId;
                    
            $edges[] = [
                        'from' => $fdiNodeId,
                        'to' => $fcvrNodeId,
                'relation' => 'num_fdi',
                'type' => 'has_fcvr'
            ];
                }
            }
        } else {
            // FCVR manquante
            $fcvrMissingNodeId = "fcvr_missing_{$fdi->ulid}";
            $nodes[] = [
                'id' => $fcvrMissingNodeId,
                'type' => 'RFCV',
                'label' => 'FCVR manquante',
                'count' => 0,
                'ulid' => null,
                'color' => $this->calculateNodeColor('FCVR', false, $fcvrReferenceDate)
            ];
        }

        // 2. Banque TVF liée (via NUM_FDI et DATE_FDI) - selon s360_analyse
        $banqueTvf = BanqueTvf::where('num_fdi', $fdi->numero_fdi)
            ->where('date_fdi', $fdi->date_fdi)
            ->first();
        
        $banqueTvfExists = $banqueTvf !== null;
        $banqueTvfReferenceDate = $fdiReferenceDate; // Date de référence = date FDI
        
        $banqueTvfNodeId = "banque_tvf_" . ($banqueTvf ? $banqueTvf->ulid : "missing_{$fdi->ulid}");
        $nodes[] = [
            'id' => $banqueTvfNodeId,
            'type' => 'AC',
            'label' => $banqueTvf ? ($banqueTvf->num_demande_ac ?? 'AC TVF') : 'AC TVF manquante',
            'count' => $banqueTvfExists ? 1 : 0,
            'ulid' => $banqueTvf ? $banqueTvf->ulid : null,
            'color' => $this->calculateNodeColor('BanqueTvf', $banqueTvfExists, $banqueTvfReferenceDate)
        ];
        
        if ($banqueTvfExists) {
            $edges[] = [
                'from' => $fdiNodeId,
                'to' => $banqueTvfNodeId,
                'relation' => 'num_fdi',
                'type' => 'has_banque_tvf'
            ];
        }

        // 3. Manifestes liés via FCVR (NUM_VOYAGE et NUM_BL) - selon s360_analyse
        $processedManifestes = [];
        foreach ($fcvrList as $fcvr) {
            if ($fcvr->num_bl && $fcvr->num_voyage) {
                $manifesteTt = ManifesteTt::where('num_titre_transport', $fcvr->num_bl)
                    ->where('num_voy_ds', $fcvr->num_voyage)
                    ->first();
                
                if ($manifesteTt) {
                    $manifeste = ManifesteSg::where('num_manifeste', $manifesteTt->num_manifeste)->first();
                    $manifesteExists = $manifeste !== null;
                    $manifesteReferenceDate = $manifeste ? ($manifeste->date_arrivee_navire ?? $manifeste->date_manifeste ?? new \DateTime()) : new \DateTime();
                    
                    $manifesteId = $manifeste ? ($manifeste->ulid ?? $manifeste->instance_id) : 'missing';
                    $manifesteNodeId = "manifeste_{$manifesteId}";
                    
                    if (!in_array($manifesteNodeId, $processedManifestes)) {
                        $nodes[] = [
                            'id' => $manifesteNodeId,
                            'type' => 'Manifeste',
                            'label' => $manifeste ? $manifeste->num_manifeste : 'Manifeste manquant',
                            'count' => $manifesteExists ? 1 : 0,
                            'ulid' => $manifeste ? ($manifeste->ulid ?? null) : null,
                            'color' => $this->calculateNodeColor('Manifeste', $manifesteExists, $manifesteReferenceDate)
                        ];
                        $processedManifestes[] = $manifesteNodeId;
                        
                        if ($fcvrExists) {
                            $fcvrNodeId = "fcvr_{$fcvr->ulid}";
                        $edges[] = [
                                'from' => $fcvrNodeId,
                                'to' => $manifesteNodeId,
                            'relation' => 'num_bl',
                            'type' => 'has_manifeste'
                        ];
                        }

                        // 4. Déclarations liées au Manifeste (via NUM_MANIFESTE et NUM_BL) - selon s360_analyse
                        $declarations = $manifeste ? DeclarationSg::where('num_manifeste', $manifeste->num_manifeste)
                            ->where('num_bl', $fcvr->num_bl)
                            ->get() : collect();
                        
                        $declarationReferenceDate = $manifesteReferenceDate;
                        
                        if ($declarations->isNotEmpty()) {
                            foreach ($declarations as $declaration) {
                            $declarationId = $declaration->ulid ?? $declaration->id;
                                $declarationNodeId = "declaration_{$declarationId}";
                                if (!in_array($declarationNodeId, $processedNodes)) {
                            $nodes[] = [
                                        'id' => $declarationNodeId,
                                'type' => 'Declaration',
                                'label' => $declaration->declaration,
                                'count' => 1,
                                'ulid' => $declaration->ulid ?? null,
                                        'color' => $this->calculateNodeColor('Declaration', true, $declarationReferenceDate)
                            ];
                                    $processedNodes['declaration_' . $declarationId] = $declarationNodeId;
                                    
                            $edges[] = [
                                        'from' => $manifesteNodeId,
                                        'to' => $declarationNodeId,
                                'relation' => 'num_manifeste',
                                'type' => 'has_declaration'
                                    ];

                                    // 5. Banque SAD liée à la Déclaration (via REF_DDU) - selon s360_analyse
                                    $banqueSad = BanqueSad::where('ref_ddu', $declaration->declaration)->first();
                                    $banqueSadExists = $banqueSad !== null;
                                    $banqueSadReferenceDate = $declaration->date_declaration ?? $declarationReferenceDate;
                                    
                                    $banqueSadId = $banqueSad ? $banqueSad->ulid : "missing_{$declarationId}";
                                    $banqueSadNodeId = "banque_sad_{$banqueSadId}";
                                    if (!in_array($banqueSadNodeId, $processedNodes)) {
                                        $nodes[] = [
                                            'id' => $banqueSadNodeId,
                                            'type' => 'AC',
                                            'label' => $banqueSad ? ($banqueSad->num_ddu ?? 'AC SAD') : 'AC SAD manquante',
                                            'count' => $banqueSadExists ? 1 : 0,
                                            'ulid' => $banqueSad ? $banqueSad->ulid : null,
                                            'color' => $this->calculateNodeColor('BanqueSad', $banqueSadExists, $banqueSadReferenceDate)
                                        ];
                                        $processedNodes['banque_sad_' . $banqueSadId] = $banqueSadNodeId;
                                        
                                        if ($banqueSadExists) {
                                            $edges[] = [
                                                'from' => $declarationNodeId,
                                                'to' => $banqueSadNodeId,
                                                'relation' => 'ref_ddu',
                                                'type' => 'has_banque_sad'
                                            ];
                                        }
                                    }

                                    // 6. Bon Provisoire lié à la Déclaration (via NUM_DECLARATION) - selon s360_analyse
                                    $bonProvisoireArticles = BonProvisoireArticle::where('num_declaration', $declaration->declaration)->get();
                                    
                                    if ($bonProvisoireArticles->isNotEmpty()) {
                                        foreach ($bonProvisoireArticles as $bpArticle) {
                                            $bonProvisoire = BonProvisoireSg::where('numero_bon_provisoire', $bpArticle->numero_bon_provisoire)->first();
                                            $bpExists = $bonProvisoire !== null;
                                            $bpReferenceDate = $declaration->date_declaration ?? $declarationReferenceDate;
                                            
                                            $bpId = $bonProvisoire ? ($bonProvisoire->ulid ?? $bonProvisoire->id) : "missing_{$declarationId}";
                                            $bpNodeId = "bon_provisoire_{$bpId}";
                                            if (!in_array($bpNodeId, $processedNodes)) {
                                                $nodes[] = [
                                                    'id' => $bpNodeId,
                                                    'type' => 'BonProvisoire',
                                                    'label' => $bonProvisoire ? ($bonProvisoire->numero_bon_provisoire_complet ?? 'BP') : 'Bon Provisoire manquant',
                                                    'count' => $bpExists ? 1 : 0,
                                                    'ulid' => $bonProvisoire ? ($bonProvisoire->ulid ?? null) : null,
                                                    'color' => $this->calculateNodeColor('BonProvisoire', $bpExists, $bpReferenceDate)
                                                ];
                                                $processedNodes['bon_provisoire_' . $bpId] = $bpNodeId;
                                                
                                                if ($bpExists) {
                                                    $edges[] = [
                                                        'from' => $declarationNodeId,
                                                        'to' => $bpNodeId,
                                                        'relation' => 'num_declaration',
                                                        'type' => 'has_bon_provisoire'
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            // Déclaration manquante
                            $declarationMissingNodeId = "declaration_missing_{$manifesteId}";
                            if (!in_array($declarationMissingNodeId, $processedNodes)) {
                                $nodes[] = [
                                    'id' => $declarationMissingNodeId,
                                    'type' => 'Declaration',
                                    'label' => 'Déclaration manquante',
                                    'count' => 0,
                                    'ulid' => null,
                                    'color' => $this->calculateNodeColor('Declaration', false, $declarationReferenceDate)
                                ];
                                $processedNodes['declaration_missing_' . $manifesteId] = $declarationMissingNodeId;
                            }
                        }
                    }
                }
            }
        }

        // 7. Déclarations liées directement à la FDI (via NUM_FDI) - selon s360_analyse
        $declarationsDirectes = DeclarationSg::where('num_fdi', $fdi->numero_fdi)->get();
        foreach ($declarationsDirectes as $declaration) {
            $declarationId = $declaration->ulid ?? $declaration->id;
            $declarationNodeId = "declaration_{$declarationId}";
            if (!in_array($declarationNodeId, $processedNodes)) {
                $declarationReferenceDate = $fdiReferenceDate; // Date de référence = date FDI
                $nodes[] = [
                    'id' => $declarationNodeId,
                    'type' => 'Declaration',
                    'label' => $declaration->declaration,
                    'count' => 1,
                    'ulid' => $declaration->ulid ?? null,
                    'color' => $this->calculateNodeColor('Declaration', true, $declarationReferenceDate)
                ];
                $processedNodes['declaration_' . $declarationId] = $declarationNodeId;
                
                $edges[] = [
                    'from' => $fdiNodeId,
                    'to' => $declarationNodeId,
                    'relation' => 'num_fdi',
                    'type' => 'has_declaration'
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Construit le diagramme de flux à partir d'une recherche Importateur
     */
    private function buildFlowDiagramFromImportateur(string $valeur, array $filters): array
    {
        $fdis = FdiSg::where('importateur', 'like', "%{$valeur}%")
            ->orWhere('cc', 'like', "%{$valeur}%")
            ->limit(10)
            ->get();

        $nodes = [];
        $edges = [];
        $processedFcvr = [];
        $processedManifestes = [];
        $processedDeclarations = [];

        foreach ($fdis as $fdi) {
            $nodes[] = [
                'id' => "fdi_{$fdi->ulid}",
                'type' => 'FDI',
                'label' => $fdi->numero_fdi,
                'count' => 1,
                'ulid' => $fdi->ulid,
                'color' => 'green'
            ];

            $fcvr = FcvrSg::where('num_fdi', $fdi->numero_fdi)->first();
            if ($fcvr && !in_array($fcvr->ulid, $processedFcvr)) {
                $nodes[] = [
                    'id' => "fcvr_{$fcvr->ulid}",
                    'type' => 'RFCV',
                    'label' => $fcvr->num_rfcv,
                    'count' => 1,
                    'ulid' => $fcvr->ulid,
                    'color' => 'grey'
                ];
                $edges[] = [
                    'from' => "fdi_{$fdi->ulid}",
                    'to' => "fcvr_{$fcvr->ulid}",
                    'relation' => 'num_fdi',
                    'type' => 'has_fcvr'
                ];
                $processedFcvr[] = $fcvr->ulid;
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Construit le diagramme de flux à partir d'une recherche Manifeste
     */
    private function buildFlowDiagramFromManifeste(string $valeur, array $filters): array
    {
        $manifeste = ManifesteSg::where('num_manifeste', 'like', "%{$valeur}%")
            ->orWhere('num_voyage', 'like', "%{$valeur}%")
            ->first();

        if (!$manifeste) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        // Date de référence pour calculer les délais (date d'arrivée du navire)
        $referenceDate = $manifeste->date_arrivee_navire ?? $manifeste->date_manifeste ?? new \DateTime();

        $manifesteId = $manifeste->ulid ?? $manifeste->instance_id;
        $manifesteNodeId = "manifeste_{$manifesteId}";
        $nodes[] = [
            'id' => $manifesteNodeId,
            'type' => 'Manifeste',
            'label' => $manifeste->num_manifeste,
            'count' => 1,
            'ulid' => $manifeste->ulid ?? null,
            'color' => $this->calculateNodeColor('Manifeste', true, $referenceDate)
        ];

        // Récupérer les titres de transport pour trouver les FCVR et Déclarations
        $manifesteTt = ManifesteTt::where('num_manifeste', $manifeste->num_manifeste)->get();
        $numBls = $manifesteTt->pluck('num_titre_transport')->filter()->unique()->toArray();
        $numVoyages = $manifesteTt->pluck('num_voy_ds')->filter()->unique()->toArray();

        // FCVR liées (via NUM_VOYAGE et NUM_BL)
        $fcvrList = [];
        if (!empty($numBls) && !empty($numVoyages)) {
            foreach ($numBls as $bl) {
                foreach ($numVoyages as $voyage) {
                    $fcvr = FcvrSg::where('num_bl', $bl)->where('num_voyage', $voyage)->first();
                    if ($fcvr && !in_array($fcvr->ulid, $fcvrList)) {
                        $fcvrList[] = $fcvr->ulid;
                        $fcvrNodeId = "fcvr_{$fcvr->ulid}";
                        $nodes[] = [
                            'id' => $fcvrNodeId,
                            'type' => 'RFCV',
                            'label' => $fcvr->num_rfcv,
                            'count' => 1,
                            'ulid' => $fcvr->ulid,
                            'color' => $this->calculateNodeColor('FCVR', true, $referenceDate)
                        ];
                        $edges[] = [
                            'from' => $manifesteNodeId,
                            'to' => $fcvrNodeId,
                            'relation' => 'num_bl',
                            'type' => 'has_fcvr'
                        ];
                    }
                }
            }
        }

        // Si pas de FCVR trouvées, créer un node "FCVR manquante"
        if (empty($fcvrList)) {
            $fcvrMissingNodeId = "fcvr_missing_{$manifesteId}";
            $nodes[] = [
                'id' => $fcvrMissingNodeId,
                'type' => 'RFCV',
                'label' => 'FCVR manquante',
                'count' => 0,
                'ulid' => null,
                'color' => $this->calculateNodeColor('FCVR', false, $referenceDate)
            ];
        }

        // Déclarations liées
        $declarations = DeclarationSg::where('num_manifeste', $manifeste->num_manifeste)->get();
        foreach ($declarations as $declaration) {
            $declarationId = $declaration->ulid ?? $declaration->id;
            $declarationNodeId = "declaration_{$declarationId}";
            $nodes[] = [
                'id' => $declarationNodeId,
                'type' => 'Declaration',
                'label' => $declaration->declaration,
                'count' => 1,
                'ulid' => $declaration->ulid ?? null,
                'color' => $this->calculateNodeColor('Declaration', true, $referenceDate)
            ];
            $edges[] = [
                'from' => $manifesteNodeId,
                'to' => $declarationNodeId,
                'relation' => 'num_manifeste',
                'type' => 'has_declaration'
            ];
        }

        // Si pas de déclaration trouvée, créer un node "Déclaration manquante"
        if ($declarations->isEmpty()) {
            $declarationMissingNodeId = "declaration_missing_{$manifesteId}";
            $nodes[] = [
                'id' => $declarationMissingNodeId,
                'type' => 'Declaration',
                'label' => 'Déclaration manquante',
                'count' => 0,
                'ulid' => null,
                'color' => $this->calculateNodeColor('Declaration', false, $referenceDate)
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Construit le diagramme de flux à partir d'une recherche RFCV
     */
    private function buildFlowDiagramFromRfcv(string $valeur, array $filters): array
    {
        $fcvr = FcvrSg::where('num_rfcv', 'like', "%{$valeur}%")
            ->orWhere('num_bl', 'like', "%{$valeur}%")
            ->first();

        if (!$fcvr) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        $nodes[] = [
            'id' => "fcvr_{$fcvr->ulid}",
            'type' => 'RFCV',
            'label' => $fcvr->num_rfcv,
            'count' => 1,
            'ulid' => $fcvr->ulid,
            'color' => 'grey'
        ];

        // FDI liée
        if ($fcvr->num_fdi) {
            $fdi = FdiSg::where('numero_fdi', $fcvr->num_fdi)->first();
            if ($fdi) {
                $nodes[] = [
                    'id' => "fdi_{$fdi->ulid}",
                    'type' => 'FDI',
                    'label' => $fdi->numero_fdi,
                    'count' => 1,
                    'ulid' => $fdi->ulid,
                    'color' => 'green'
                ];
                $edges[] = [
                    'from' => "fdi_{$fdi->ulid}",
                    'to' => "fcvr_{$fcvr->ulid}",
                    'relation' => 'num_fdi',
                    'type' => 'has_fcvr'
                ];
            }
        }

        // Manifeste lié via BL
        if ($fcvr->num_bl) {
            $manifesteTt = ManifesteTt::where('num_titre_transport', $fcvr->num_bl)->first();
            if ($manifesteTt) {
                $manifeste = ManifesteSg::where('num_manifeste', $manifesteTt->num_manifeste)->first();
                if ($manifeste) {
                    $manifesteId = $manifeste->ulid ?? $manifeste->instance_id;
                    $nodes[] = [
                        'id' => "manifeste_{$manifesteId}",
                        'type' => 'Manifeste',
                        'label' => $manifeste->num_manifeste,
                        'count' => 1,
                        'ulid' => $manifeste->ulid ?? null,
                        'color' => 'grey'
                    ];
                    $edges[] = [
                        'from' => "fcvr_{$fcvr->ulid}",
                        'to' => "manifeste_{$manifesteId}",
                        'relation' => 'num_bl',
                        'type' => 'has_manifeste'
                    ];
                }
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Construit le diagramme de flux à partir d'une recherche Déclaration
     */
    private function buildFlowDiagramFromDeclaration(string $valeur, array $filters): array
    {
        $declaration = DeclarationSg::where('declaration', 'like', "%{$valeur}%")
            ->first();

        if (!$declaration) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        $declarationId = $declaration->ulid ?? $declaration->id;
        $nodes[] = [
            'id' => "declaration_{$declarationId}",
            'type' => 'Declaration',
            'label' => $declaration->declaration,
            'count' => 1,
            'ulid' => $declaration->ulid ?? null,
            'color' => 'red'
        ];

        // Manifeste lié
        if ($declaration->num_manifeste) {
            $manifeste = ManifesteSg::where('num_manifeste', $declaration->num_manifeste)->first();
            if ($manifeste) {
                $manifesteId = $manifeste->ulid ?? $manifeste->instance_id;
                $nodes[] = [
                    'id' => "manifeste_{$manifesteId}",
                    'type' => 'Manifeste',
                    'label' => $manifeste->num_manifeste,
                    'count' => 1,
                    'ulid' => $manifeste->ulid ?? null,
                    'color' => 'grey'
                ];
                $edges[] = [
                    'from' => "manifeste_{$manifesteId}",
                    'to' => "declaration_{$declarationId}",
                    'relation' => 'num_manifeste',
                    'type' => 'has_declaration'
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Navigation entre entités
     */
    public function navigate(string $fromType, string $fromIdentifier, string $toType): array
    {
        // Normaliser rfcv -> fcvr
        $fromType = $fromType === 'rfcv' ? 'fcvr' : $fromType;
        $toType = $toType === 'rfcv' ? 'fcvr' : $toType;
        
        $fromModel = $this->getModelByType($fromType);
        
        // Chercher par différents identifiants selon le type
        $query = $fromModel::where('ulid', $fromIdentifier);
        
        // Ajouter les conditions selon le type
        switch ($fromType) {
            case 'fdi':
                $query->orWhere('numero_fdi', $fromIdentifier);
                if (is_numeric($fromIdentifier)) {
                    $query->orWhere('id', (int) $fromIdentifier);
                }
                break;
            case 'fcvr':
                $query->orWhere('num_rfcv', $fromIdentifier)
                      ->orWhere('instanceid', $fromIdentifier);
                if (is_numeric($fromIdentifier)) {
                    $query->orWhere('id', (int) $fromIdentifier);
                }
                break;
            case 'manifeste':
                $query->orWhere('num_manifeste', $fromIdentifier)
                      ->orWhere('instance_id', $fromIdentifier);
                if (is_numeric($fromIdentifier)) {
                    $query->orWhere('id', (int) $fromIdentifier);
                }
                break;
            case 'declaration':
                $query->orWhere('declaration', $fromIdentifier);
                if (is_numeric($fromIdentifier)) {
                    $query->orWhere('id', (int) $fromIdentifier);
                }
                break;
        }
        
        $fromRecord = $query->first();

        if (!$fromRecord) {
            return ['from' => null, 'to' => []];
        }

        $toRecords = collect([]);

        switch ($fromType) {
            case 'fdi':
                if ($toType === 'fcvr') {
                    $toRecords = FcvrSg::where('num_fdi', $fromRecord->numero_fdi)->get();
                } elseif ($toType === 'declaration') {
                    $toRecords = DeclarationSg::where('num_fdi', $fromRecord->numero_fdi)->get();
                }
                break;
            case 'fcvr':
                if ($toType === 'fdi' && $fromRecord->num_fdi) {
                    $toRecords = FdiSg::where('numero_fdi', $fromRecord->num_fdi)->get();
                } elseif ($toType === 'manifeste' && $fromRecord->num_bl) {
                    $manifesteTt = ManifesteTt::where('num_titre_transport', $fromRecord->num_bl)->first();
                    if ($manifesteTt) {
                        $toRecords = ManifesteSg::where('num_manifeste', $manifesteTt->num_manifeste)->get();
                    }
                }
                break;
            case 'manifeste':
                if ($toType === 'declaration') {
                    $toRecords = DeclarationSg::where('num_manifeste', $fromRecord->num_manifeste)->get();
                } elseif ($toType === 'fcvr') {
                    $manifesteTt = ManifesteTt::where('num_manifeste', $fromRecord->num_manifeste)->get();
                    $numBls = $manifesteTt->isNotEmpty() ? $manifesteTt->pluck('num_titre_transport')->toArray() : [];
                    if (!empty($numBls)) {
                        $toRecords = FcvrSg::whereIn('num_bl', $numBls)->get();
                    }
                }
                break;
            case 'declaration':
                if ($toType === 'manifeste' && $fromRecord->num_manifeste) {
                    $toRecords = ManifesteSg::where('num_manifeste', $fromRecord->num_manifeste)->get();
                } elseif ($toType === 'fdi' && $fromRecord->num_fdi) {
                    $toRecords = FdiSg::where('numero_fdi', $fromRecord->num_fdi)->get();
                }
                break;
        }

        // S'assurer que $toRecords est toujours une Collection
        if (!($toRecords instanceof Collection)) {
            $toRecords = collect($toRecords ?: []);
        }

        return [
            'from' => [
                'type' => $fromType,
                'ulid' => $fromRecord->ulid,
                'label' => $this->getLabel($fromRecord, $fromType)
            ],
            'to' => $toRecords->map(function ($record) use ($toType, $fromType) {
                return [
                    'type' => $toType,
                    'ulid' => $record->ulid ?? $record->id ?? null,
                    'label' => $this->getLabel($record, $toType),
                    'relation' => $this->getRelationType($fromType, $toType)
                ];
            })->toArray()
        ];
    }

    /**
     * Récupère les données liées à partir d'une collection de FDI
     */
    private function getRelatedDataFromFdi($fdiCollection, int $perPage, int $page): array
    {
        $fdiIds = [];
        if ($fdiCollection) {
            $fdiIds = $fdiCollection->pluck('numero_fdi')->filter()->toArray();
        }
        
        $fdiArticles = null;
        if (!empty($fdiIds)) {
            $fdiArticles = FdiArticle::whereIn('numero_fdi', $fdiIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        $fcvrGeneral = null;
        if (!empty($fdiIds)) {
            $fcvrGeneral = FcvrSg::whereIn('num_fdi', $fdiIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        $fcvrInstanceIds = [];
        if ($fcvrGeneral) {
            $fcvrInstanceIds = $fcvrGeneral->pluck('instanceid')->filter()->toArray();
        }
        $fcvrArticles = null;
        if (!empty($fcvrInstanceIds)) {
            $fcvrArticles = FcvrArticle::whereIn('instanceid', $fcvrInstanceIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        $numBls = [];
        if ($fcvrGeneral) {
            $numBls = $fcvrGeneral->pluck('num_bl')->filter()->toArray();
        }
        $manifesteGeneral = null;
        $manifesteTt = null;
        $manifesteTc = null;
        
        if (!empty($numBls)) {
            $manifesteTtQuery = ManifesteTt::whereIn('num_titre_transport', $numBls)->get();
            $numManifestes = $manifesteTtQuery->pluck('num_manifeste')->filter()->unique()->toArray();
            
            if (!empty($numManifestes)) {
                $manifesteGeneral = ManifesteSg::whereIn('num_manifeste', $numManifestes)
                    ->paginate($perPage, ['*'], 'page', $page);
                $manifesteTt = $manifesteTtQuery->slice(($page - 1) * $perPage, $perPage)->values();
                $manifesteTc = ManifesteTc::withoutGlobalScopes()
                    ->whereIn('num_manifeste', $numManifestes)
                    ->paginate($perPage, ['*'], 'page', $page);
            }
        }

        return [
            'fdi_general' => $this->extractItems($fdiCollection),
            'fdi_article' => $this->extractItems($fdiArticles),
            'fcvr_general' => $this->extractItems($fcvrGeneral),
            'fcvr_article' => $this->extractItems($fcvrArticles),
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => $this->extractItems($manifesteTt),
            'manifeste_tc' => $this->extractItems($manifesteTc),
        ];
    }

    /**
     * Applique les filtres communs
     */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        $table = $query->getModel()->getTable();
        
        if (isset($filters['annee'])) {
            $anneeColumn = $this->getAnneeColumn($table);
            if ($anneeColumn) {
                $query->where($anneeColumn, $filters['annee']);
            }
        }
        
        if (isset($filters['bureau'])) {
            $bureauColumn = $this->getBureauColumn($table);
            if ($bureauColumn) {
                $query->where($bureauColumn, 'like', "%{$filters['bureau']}%");
            }
        }
        
        if (isset($filters['date_min'])) {
            $dateColumn = $this->getDateColumn($table);
            if ($dateColumn) {
                $query->whereDate($dateColumn, '>=', $filters['date_min']);
            }
        }
        
        if (isset($filters['date_max'])) {
            $dateColumn = $this->getDateColumn($table);
            if ($dateColumn) {
                $query->whereDate($dateColumn, '<=', $filters['date_max']);
            }
        }
    }

    /**
     * Récupère la colonne année selon la table
     */
    private function getAnneeColumn(string $table): ?string
    {
        return match($table) {
            'fdi_sg' => 'annee',
            'fcvr_sg' => 'annee',
            'declaration_sg' => 'annee',
            default => null
        };
    }

    /**
     * Récupère la colonne bureau selon la table
     */
    private function getBureauColumn(string $table): ?string
    {
        return match($table) {
            'fdi_sg' => 'bureau',
            'fcvr_sg' => 'bureau',
            'declaration_sg' => 'bureau',
            'manifeste_sg' => 'code_bureau',
            default => null
        };
    }

    /**
     * Récupère la colonne date selon la table
     */
    private function getDateColumn(string $table): ?string
    {
        return match($table) {
            'fdi_sg' => 'date_fdi',
            'fcvr_sg' => 'date_rfcv',
            'declaration_sg' => 'date_declaration',
            'manifeste_sg' => 'date_manifeste',
            default => null
        };
    }

    /**
     * Récupère le modèle selon le type
     */
    private function getModelByType(string $type): string
    {
        return match($type) {
            'fdi' => FdiSg::class,
            'fcvr', 'rfcv' => FcvrSg::class,
            'manifeste' => ManifesteSg::class,
            'declaration' => DeclarationSg::class,
            default => throw new \InvalidArgumentException("Type invalide: {$type}")
        };
    }

    /**
     * Récupère le label d'un enregistrement
     */
    private function getLabel($record, string $type): string
    {
        return match($type) {
            'fdi' => $record->numero_fdi ?? '',
            'fcvr', 'rfcv' => $record->num_rfcv ?? '',
            'manifeste' => $record->num_manifeste ?? '',
            'declaration' => $record->declaration ?? '',
            default => ''
        };
    }

    /**
     * Récupère le type de relation
     */
    private function getRelationType(string $from, string $to): string
    {
        $relations = [
            'fdi-fcvr' => 'num_fdi',
            'fcvr-fdi' => 'num_fdi',
            'fcvr-manifeste' => 'num_bl',
            'manifeste-fcvr' => 'num_bl',
            'manifeste-declaration' => 'num_manifeste',
            'declaration-manifeste' => 'num_manifeste',
            'fdi-declaration' => 'num_fdi',
            'declaration-fdi' => 'num_fdi',
        ];

        return $relations["{$from}-{$to}"] ?? 'unknown';
    }

    /**
     * Récupère les résultats par onglet
     */
    public function getResultsByTab(string $option, string $valeur, string $tab, array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $allResults = $this->searchAdvanced($option, $valeur, $filters, $perPage, $page);
        
        $results = $allResults['results'][$tab] ?? [];
        
        // Si c'est une collection, convertir en array
        if ($results instanceof Collection) {
            return $results->toArray();
        }
        
        // Si c'est un array avec des objets, convertir en array simple
        if (is_array($results)) {
            return array_map(function($item) {
                return $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : $item;
            }, $results);
        }
        
        return [];
    }

    /**
     * Recherche par Manifeste TT (Option 1)
     */
    private function searchByManifesteTt(string $valeur, array $filters, int $perPage, int $page): array
    {
        $query = ManifesteTt::query()
            ->where(function ($q) use ($valeur) {
                $q->where('num_titre_transport', 'like', "%{$valeur}%")
                  ->orWhere('num_voy_ds', 'like', "%{$valeur}%")
                  ->orWhere('num_manifeste', 'like', "%{$valeur}%");
            });

        $manifesteTt = $query->paginate($perPage, ['*'], 'page', $page);
        
        // Récupérer les manifestes associés
        $numManifestes = [];
        if ($manifesteTt) {
            $numManifestes = $manifesteTt->pluck('num_manifeste')->filter()->unique()->toArray();
        }
        
        $manifesteGeneral = null;
        if (!empty($numManifestes)) {
            $manifesteGeneral = ManifesteSg::whereIn('num_manifeste', $numManifestes)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => $this->extractItems($manifesteTt),
            'manifeste_tc' => [],
            'fcvr_general' => [],
            'fcvr_article' => [],
            'fdi_general' => [],
            'fdi_article' => [],
        ];
    }

    /**
     * Recherche par AC (Autorisation de Crédit) (Option 1)
     */
    private function searchByAc(string $valeur, array $filters, int $perPage, int $page): array
    {
        // Recherche dans Banque SAD (AC effectifs)
        $banqueSad = BanqueSad::where('num_ddu', 'like', "%{$valeur}%")
            ->orWhere('ref_ddu', 'like', "%{$valeur}%")
            ->orWhere('num_demande_ac', 'like', "%{$valeur}%")
            ->paginate($perPage, ['*'], 'page', $page);

        // Recherche dans Banque TVF (AC non effectifs)
        $banqueTvf = BanqueTvf::where('num_demande_ac', 'like', "%{$valeur}%")
            ->orWhere('ref_ddu', 'like', "%{$valeur}%")
            ->paginate($perPage, ['*'], 'page', $page);

        // Récupérer les déclarations liées
        $declarationIds = [];
        if ($banqueSad) {
            $declarationIds = array_merge($declarationIds, $banqueSad->pluck('ref_ddu')->filter()->toArray());
        }
        
        $declarations = null;
        if (!empty($declarationIds)) {
            $declarations = DeclarationSg::whereIn('declaration', $declarationIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'banque_sad' => $this->extractItems($banqueSad),
            'banque_tvf' => $this->extractItems($banqueTvf),
            'declaration_general' => $this->extractItems($declarations),
            'declaration_article' => [],
            'fdi_general' => [],
            'fdi_article' => [],
            'fcvr_general' => [],
            'fcvr_article' => [],
            'manifeste_general' => [],
            'manifeste_tt' => [],
            'manifeste_tc' => [],
        ];
    }

    /**
     * Recherche par Année (Option 2)
     */
    private function searchByAnnee(string $valeur, array $filters, int $perPage, int $page): array
    {
        $annee = (int) $valeur;
        
        // Recherche dans toutes les entités par année
        $fdiGeneral = FdiSg::where('annee', $annee)->paginate($perPage, ['*'], 'page', $page);
        $fcvrGeneral = FcvrSg::where('annee', $annee)->paginate($perPage, ['*'], 'page', $page);
        $declarations = DeclarationSg::where('annee', $annee)->paginate($perPage, ['*'], 'page', $page);
        $manifesteGeneral = ManifesteSg::where('annee_manifeste', $annee)->paginate($perPage, ['*'], 'page', $page);

        return [
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => [],
            'fcvr_general' => $this->extractItems($fcvrGeneral),
            'fcvr_article' => [],
            'declaration_general' => $this->extractItems($declarations),
            'declaration_article' => [],
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => [],
            'manifeste_tc' => [],
        ];
    }

    /**
     * Recherche par Déclarant (Option 2)
     */
    private function searchByDeclarant(string $valeur, array $filters, int $perPage, int $page): array
    {
        $query = DeclarationSg::query()
            ->where(function ($q) use ($valeur) {
                $q->where('declarant', 'like', "%{$valeur}%")
                  ->orWhere('codagr', 'like', "%{$valeur}%");
            });

        $declarations = $query->paginate($perPage, ['*'], 'page', $page);
        
        // Récupérer les articles
        $declarationIds = [];
        if ($declarations) {
            $declarationIds = $declarations->pluck('declaration')->filter()->toArray();
        }
        
        $declarationArticles = null;
        if (!empty($declarationIds)) {
            $declarationArticles = DeclarationArticle::whereIn('declaration', $declarationIds)
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'declaration_general' => $this->extractItems($declarations),
            'declaration_article' => $this->extractItems($declarationArticles),
            'fdi_general' => [],
            'fdi_article' => [],
            'fcvr_general' => [],
            'fcvr_article' => [],
            'manifeste_general' => [],
            'manifeste_tt' => [],
            'manifeste_tc' => [],
        ];
    }

    /**
     * Recherche par Bureau/Port (Option 2)
     */
    private function searchByBureauPort(string $valeur, array $filters, int $perPage, int $page): array
    {
        // Recherche dans toutes les entités par bureau/port
        $fdiGeneral = FdiSg::where('bureau', 'like', "%{$valeur}%")->paginate($perPage, ['*'], 'page', $page);
        $fcvrGeneral = FcvrSg::where('bureau', 'like', "%{$valeur}%")->paginate($perPage, ['*'], 'page', $page);
        $declarations = DeclarationSg::where('bureau', 'like', "%{$valeur}%")->paginate($perPage, ['*'], 'page', $page);
        $manifesteGeneral = ManifesteSg::where('code_bureau', 'like', "%{$valeur}%")
            ->orWhere('nom_bureau', 'like', "%{$valeur}%")
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => [],
            'fcvr_general' => $this->extractItems($fcvrGeneral),
            'fcvr_article' => [],
            'declaration_general' => $this->extractItems($declarations),
            'declaration_article' => [],
            'manifeste_general' => $this->extractItems($manifesteGeneral),
            'manifeste_tt' => [],
            'manifeste_tc' => [],
        ];
    }

    /**
     * Recherche par Pays Exportateur (Option 2)
     */
    private function searchByPaysExportateur(string $valeur, array $filters, int $perPage, int $page): array
    {
        // Recherche dans FDI et Déclarations par pays exportateur/fournisseur
        $fdiGeneral = FdiSg::where('pays_fournisseur', 'like', "%{$valeur}%")
            ->orWhere('pays_exportateur', 'like', "%{$valeur}%")
            ->paginate($perPage, ['*'], 'page', $page);
        
        $declarations = DeclarationSg::where('provenance', 'like', "%{$valeur}%")
            ->orWhere('pays_exportateur', 'like', "%{$valeur}%")
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'fdi_general' => $this->extractItems($fdiGeneral),
            'fdi_article' => [],
            'fcvr_general' => [],
            'fcvr_article' => [],
            'declaration_general' => $this->extractItems($declarations),
            'declaration_article' => [],
            'manifeste_general' => [],
            'manifeste_tt' => [],
            'manifeste_tc' => [],
        ];
    }

    /**
     * Construit le diagramme de flux pour Manifeste TT
     */
    private function buildFlowDiagramFromManifesteTt(string $valeur, array $filters): array
    {
        $manifesteTt = ManifesteTt::where('num_titre_transport', 'like', "%{$valeur}%")
            ->orWhere('num_voy_ds', 'like', "%{$valeur}%")
            ->first();

        if (!$manifesteTt) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        // Node Manifeste TT
        $nodes[] = [
            'id' => "manifeste_tt_{$manifesteTt->instance_id}",
            'type' => 'ManifesteTT',
            'label' => $manifesteTt->num_titre_transport,
            'count' => 1,
            'ulid' => null,
            'color' => 'blue'
        ];

        // Manifeste associé
        if ($manifesteTt->num_manifeste) {
            $manifeste = ManifesteSg::where('num_manifeste', $manifesteTt->num_manifeste)->first();
            if ($manifeste) {
                $manifesteId = $manifeste->ulid ?? $manifeste->instance_id;
                $nodes[] = [
                    'id' => "manifeste_{$manifesteId}",
                    'type' => 'Manifeste',
                    'label' => $manifeste->num_manifeste,
                    'count' => 1,
                    'ulid' => $manifeste->ulid ?? null,
                    'color' => 'grey'
                ];
                $edges[] = [
                    'from' => "manifeste_tt_{$manifesteTt->instance_id}",
                    'to' => "manifeste_{$manifesteId}",
                    'relation' => 'num_manifeste',
                    'type' => 'has_manifeste'
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Construit le diagramme de flux pour AC
     */
    private function buildFlowDiagramFromAc(string $valeur, array $filters): array
    {
        $banqueSad = BanqueSad::where('num_ddu', 'like', "%{$valeur}%")
            ->orWhere('ref_ddu', 'like', "%{$valeur}%")
            ->first();

        if ($banqueSad) {
            $declaration = DeclarationSg::where('declaration', $banqueSad->ref_ddu)->first();
            if ($declaration) {
                return [
                    'nodes' => [
                        [
                            'id' => "ac_{$banqueSad->ulid}",
                            'type' => 'AC',
                            'label' => $banqueSad->num_ddu ?? $banqueSad->ref_ddu,
                            'count' => 1,
                            'ulid' => $banqueSad->ulid,
                            'color' => 'orange'
                        ],
                        [
                            'id' => "declaration_{$declaration->ulid}",
                            'type' => 'Declaration',
                            'label' => $declaration->declaration,
                            'count' => 1,
                            'ulid' => $declaration->ulid,
                            'color' => 'red'
                        ]
                    ],
                    'edges' => [
                        [
                            'from' => "ac_{$banqueSad->ulid}",
                            'to' => "declaration_{$declaration->ulid}",
                            'relation' => 'ref_ddu',
                            'type' => 'has_declaration'
                        ]
                    ]
                ];
            }
        }

        return ['nodes' => [], 'edges' => []];
    }

    /**
     * Construit le diagramme de flux pour Année
     */
    private function buildFlowDiagramFromAnnee(string $valeur, array $filters): array
    {
        $annee = (int) $valeur;
        $nodes = [];
        $edges = [];

        // Compter les entités par année
        $fdiCount = FdiSg::where('annee', $annee)->count();
        $fcvrCount = FcvrSg::where('annee', $annee)->count();
        $declarationCount = DeclarationSg::where('annee', $annee)->count();
        $manifesteCount = ManifesteSg::where('annee_manifeste', $annee)->count();

        if ($fdiCount > 0) {
            $nodes[] = ['id' => "fdi_annee_{$annee}", 'type' => 'FDI', 'label' => "FDI {$annee}", 'count' => $fdiCount, 'ulid' => null, 'color' => 'green'];
        }
        if ($fcvrCount > 0) {
            $nodes[] = ['id' => "fcvr_annee_{$annee}", 'type' => 'FCVR', 'label' => "FCVR {$annee}", 'count' => $fcvrCount, 'ulid' => null, 'color' => 'grey'];
        }
        if ($declarationCount > 0) {
            $nodes[] = ['id' => "declaration_annee_{$annee}", 'type' => 'Declaration', 'label' => "Déclarations {$annee}", 'count' => $declarationCount, 'ulid' => null, 'color' => 'red'];
        }
        if ($manifesteCount > 0) {
            $nodes[] = ['id' => "manifeste_annee_{$annee}", 'type' => 'Manifeste', 'label' => "Manifestes {$annee}", 'count' => $manifesteCount, 'ulid' => null, 'color' => 'blue'];
        }

        return ['nodes' => $nodes, 'edges' => []];
    }

    /**
     * Construit le diagramme de flux pour Déclarant
     */
    private function buildFlowDiagramFromDeclarant(string $valeur, array $filters): array
    {
        $declarations = DeclarationSg::where('declarant', 'like', "%{$valeur}%")
            ->orWhere('codagr', 'like', "%{$valeur}%")
            ->limit(10)
            ->get();

        $nodes = [];
        $edges = [];

        foreach ($declarations as $declaration) {
            $declarationId = $declaration->ulid ?? $declaration->id;
            $nodes[] = [
                'id' => "declaration_{$declarationId}",
                'type' => 'Declaration',
                'label' => $declaration->declaration,
                'count' => 1,
                'ulid' => $declaration->ulid ?? null,
                'color' => 'red'
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Construit le diagramme de flux pour Bureau/Port
     */
    private function buildFlowDiagramFromBureauPort(string $valeur, array $filters): array
    {
        $nodes = [];
        $edges = [];

        // Compter les entités par bureau
        $fdiCount = FdiSg::where('bureau', 'like', "%{$valeur}%")->count();
        $fcvrCount = FcvrSg::where('bureau', 'like', "%{$valeur}%")->count();
        $declarationCount = DeclarationSg::where('bureau', 'like', "%{$valeur}%")->count();
        $manifesteCount = ManifesteSg::where('code_bureau', 'like', "%{$valeur}%")->count();

        if ($fdiCount > 0) {
            $nodes[] = ['id' => "fdi_bureau_{$valeur}", 'type' => 'FDI', 'label' => "FDI {$valeur}", 'count' => $fdiCount, 'ulid' => null, 'color' => 'green'];
        }
        if ($fcvrCount > 0) {
            $nodes[] = ['id' => "fcvr_bureau_{$valeur}", 'type' => 'FCVR', 'label' => "FCVR {$valeur}", 'count' => $fcvrCount, 'ulid' => null, 'color' => 'grey'];
        }
        if ($declarationCount > 0) {
            $nodes[] = ['id' => "declaration_bureau_{$valeur}", 'type' => 'Declaration', 'label' => "Déclarations {$valeur}", 'count' => $declarationCount, 'ulid' => null, 'color' => 'red'];
        }
        if ($manifesteCount > 0) {
            $nodes[] = ['id' => "manifeste_bureau_{$valeur}", 'type' => 'Manifeste', 'label' => "Manifestes {$valeur}", 'count' => $manifesteCount, 'ulid' => null, 'color' => 'blue'];
        }

        return ['nodes' => $nodes, 'edges' => []];
    }

    /**
     * Construit le diagramme de flux pour Pays Exportateur
     */
    private function buildFlowDiagramFromPaysExportateur(string $valeur, array $filters): array
    {
        $nodes = [];
        $edges = [];

        $fdiCount = FdiSg::where('pays_fournisseur', 'like', "%{$valeur}%")->count();
        $declarationCount = DeclarationSg::where('provenance', 'like', "%{$valeur}%")->count();

        if ($fdiCount > 0) {
            $nodes[] = ['id' => "fdi_pays_{$valeur}", 'type' => 'FDI', 'label' => "FDI {$valeur}", 'count' => $fdiCount, 'ulid' => null, 'color' => 'green'];
        }
        if ($declarationCount > 0) {
            $nodes[] = ['id' => "declaration_pays_{$valeur}", 'type' => 'Declaration', 'label' => "Déclarations {$valeur}", 'count' => $declarationCount, 'ulid' => null, 'color' => 'red'];
        }

        return ['nodes' => $nodes, 'edges' => []];
    }

    /**
     * Helper pour extraire les items d'un paginator, collection ou array
     */
    private function extractItems($data): array
    {
        if ($data === null) {
            return [];
        }

        if ($data instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            return $data->items();
        }

        if ($data instanceof Collection) {
            return $data->all();
        }

        if (is_array($data)) {
            return $data;
        }

        return [];
    }

    /**
     * Récupère les détails complets d'un nœud pour affichage dans un modal
     * Accepte ULID, numero_fdi, id, ou autres identifiants selon le type
     */
    public function getNodeDetails(string $type, string $identifier): ?array
    {
        $type = $type === 'rfcv' ? 'fcvr' : $type;
        
        switch ($type) {
            case 'fdi':
                // Chercher par ULID, numero_fdi, ou id (si numérique)
                $query = FdiSg::where('ulid', $identifier)
                    ->orWhere('numero_fdi', $identifier);
                
                // Ajouter la condition id seulement si l'identifiant est numérique
                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
                
                $fdi = $query->with('articles')->first();
                if (!$fdi) {
                    return null;
                }
                
                // Nettoyer les données : retirer deleted_at, ulid, et articles de data
                $fdiData = $fdi->toArray();
                unset($fdiData['deleted_at'], $fdiData['ulid'], $fdiData['articles']);
                
                // Nettoyer les relations : retirer deleted_at et ulid
                $fcvrRelated = FcvrSg::where('num_fdi', $fdi->numero_fdi)->get()->map(function($item) {
                    $data = $item->toArray();
                    unset($data['deleted_at'], $data['ulid']);
                    return $data;
                })->toArray();
                
                $declarationsRelated = DeclarationSg::where('num_fdi', $fdi->numero_fdi)->get()->map(function($item) {
                    $data = $item->toArray();
                    unset($data['deleted_at'], $data['ulid']);
                    return $data;
                })->toArray();
                
                return [
                    'type' => 'fdi',
                    'ulid' => $fdi->ulid,
                    'data' => $fdiData,
                    'articles' => $fdi->articles->toArray(),
                    'related' => [
                        'fcvr' => $fcvrRelated,
                        'declarations' => $declarationsRelated,
                    ],
                ];
                
            case 'fcvr':
                // Chercher par ULID, num_rfcv, instanceid, ou id (si numérique)
                $query = FcvrSg::where('ulid', $identifier)
                    ->orWhere('num_rfcv', $identifier)
                    ->orWhere('instanceid', $identifier);
                
                // Ajouter la condition id seulement si l'identifiant est numérique
                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
                
                $fcvr = $query->with('articles')->first();
                if (!$fcvr) {
                    return null;
                }
                
                // Nettoyer les données : retirer deleted_at, ulid, et articles de data
                $fcvrData = $fcvr->toArray();
                unset($fcvrData['deleted_at'], $fcvrData['ulid'], $fcvrData['articles']);
                
                // Nettoyer les relations : retirer deleted_at et ulid
                $fdiRelated = FdiSg::where('numero_fdi', $fcvr->num_fdi)->first();
                $fdiRelatedData = $fdiRelated ? $fdiRelated->toArray() : null;
                if ($fdiRelatedData) {
                    unset($fdiRelatedData['deleted_at'], $fdiRelatedData['ulid']);
                }
                
                $manifesteRelated = $this->getManifesteByBl($fcvr->num_bl);
                if ($manifesteRelated && isset($manifesteRelated['ulid'])) {
                    unset($manifesteRelated['ulid'], $manifesteRelated['deleted_at']);
                }
                
                return [
                    'type' => 'fcvr',
                    'ulid' => $fcvr->ulid,
                    'data' => $fcvrData,
                    'articles' => $fcvr->articles->toArray(),
                    'related' => [
                        'fdi' => $fdiRelatedData,
                        'manifeste' => $manifesteRelated,
                    ],
                ];
                
            case 'manifeste':
                // Chercher par ULID, num_manifeste, instance_id, ou id (si numérique)
                $query = ManifesteSg::where('ulid', $identifier)
                    ->orWhere('num_manifeste', $identifier)
                    ->orWhere('instance_id', $identifier);
                
                // Ajouter la condition id seulement si l'identifiant est numérique
                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
                
                $manifeste = $query->first();
                if (!$manifeste) {
                    return null;
                }
                
                // Nettoyer les données : retirer deleted_at et ulid de data
                $manifesteData = $manifeste->toArray();
                unset($manifesteData['deleted_at'], $manifesteData['ulid']);
                
                // Nettoyer les relations : retirer deleted_at et ulid
                $declarationsRelated = DeclarationSg::where('num_manifeste', $manifeste->num_manifeste)->get()->map(function($item) {
                    $data = $item->toArray();
                    unset($data['deleted_at'], $data['ulid']);
                    return $data;
                })->toArray();
                
                $conteneursRelated = ManifesteTc::withoutGlobalScopes()
                    ->where('num_manifeste', $manifeste->num_manifeste)
                    ->get()
                    ->toArray();
                
                return [
                    'type' => 'manifeste',
                    'ulid' => $manifeste->ulid,
                    'data' => $manifesteData,
                    'related' => [
                        'declarations' => $declarationsRelated,
                        'conteneurs' => $conteneursRelated,
                    ],
                ];
                
            case 'declaration':
                // Chercher par ULID, declaration, ou id (si numérique)
                $query = DeclarationSg::where('ulid', $identifier)
                    ->orWhere('declaration', $identifier);
                
                // Ajouter la condition id seulement si l'identifiant est numérique
                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
                
                $declaration = $query->first();
                if (!$declaration) {
                    return null;
                }
                
                // Nettoyer les données : retirer deleted_at et ulid de data
                $declarationData = $declaration->toArray();
                unset($declarationData['deleted_at'], $declarationData['ulid']);
                
                // Nettoyer les relations : retirer deleted_at et ulid
                $manifesteRelated = ManifesteSg::where('num_manifeste', $declaration->num_manifeste)->first();
                $manifesteRelatedData = $manifesteRelated ? $manifesteRelated->toArray() : null;
                if ($manifesteRelatedData) {
                    unset($manifesteRelatedData['deleted_at'], $manifesteRelatedData['ulid']);
                }
                
                return [
                    'type' => 'declaration',
                    'ulid' => $declaration->ulid,
                    'data' => $declarationData,
                    'related' => [
                        'manifeste' => $manifesteRelatedData,
                        'articles' => \App\Models\DeclarationArticle::where('declaration_id', $declaration->id)->get()->toArray(),
                        'conteneurs' => \App\Models\DeclarationTc::where('declaration_id', $declaration->id)->get()->toArray(),
                    ],
                ];
                
            default:
                return null;
        }
    }

    /**
     * Helper pour récupérer un manifeste par BL
     */
    private function getManifesteByBl(?string $numBl): ?array
    {
        if (!$numBl) {
            return null;
        }
        
        $manifesteTt = ManifesteTt::where('num_titre_transport', $numBl)->first();
        if (!$manifesteTt) {
            return null;
        }
        
        $manifeste = ManifesteSg::where('num_manifeste', $manifesteTt->num_manifeste)->first();
        return $manifeste?->toArray();
    }
}
