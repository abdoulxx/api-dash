<?php

namespace App\Services;

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
     */
    public function getSearchOptions(): array
    {
        return [
            [
                'value' => 'FDI',
                'label' => 'FDI',
                'fields' => ['numero_fdi', 'annee', 'bureau', 'importateur'],
                'description' => 'Recherche par numéro FDI, année, bureau ou importateur'
            ],
            [
                'value' => 'Importateur',
                'label' => 'Importateur',
                'fields' => ['importateur', 'cc'],
                'description' => 'Recherche par nom d\'importateur ou code importateur'
            ],
            [
                'value' => 'Manifeste',
                'label' => 'Manifeste',
                'fields' => ['num_manifeste', 'num_voyage', 'nom_moyen_transport', 'nom_transport'],
                'description' => 'Recherche par numéro de manifeste, voyage ou moyen de transport'
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
            ];

            $flowDiagram = ['nodes' => [], 'edges' => []];

            switch (strtolower($option)) {
                case 'fdi':
                    $results = $this->searchByFdi($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromFdi($valeur, $filters);
                    break;
                case 'importateur':
                    $results = $this->searchByImportateur($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromImportateur($valeur, $filters);
                    break;
                case 'manifeste':
                    $results = $this->searchByManifeste($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromManifeste($valeur, $filters);
                    break;
                case 'rfcv':
                    $results = $this->searchByRfcv($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromRfcv($valeur, $filters);
                    break;
                case 'declaration':
                    $results = $this->searchByDeclaration($valeur, $filters, $perPage, $page);
                    $flowDiagram = $this->buildFlowDiagramFromDeclaration($valeur, $filters);
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
                  ->orWhere('nom_transport', 'like', "%{$valeur}%");
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
     * Construit le diagramme de flux à partir d'une recherche FDI
     */
    private function buildFlowDiagramFromFdi(string $valeur, array $filters): array
    {
        $fdi = FdiSg::where('numero_fdi', 'like', "%{$valeur}%")
            ->orWhere('importateur', 'like', "%{$valeur}%")
            ->first();

        if (!$fdi) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        // Node FDI
        $nodes[] = [
            'id' => "fdi_{$fdi->ulid}",
            'type' => 'FDI',
            'label' => $fdi->numero_fdi,
            'count' => 1,
            'ulid' => $fdi->ulid,
            'color' => 'green'
        ];

        // RFCV liées
        $fcvr = FcvrSg::where('num_fdi', $fdi->numero_fdi)->first();
        if ($fcvr) {
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

                        // Déclaration liée
                        $declaration = DeclarationSg::where('num_manifeste', $manifeste->num_manifeste)->first();
                        if ($declaration) {
                            $declarationId = $declaration->ulid ?? $declaration->id;
                            $nodes[] = [
                                'id' => "declaration_{$declarationId}",
                                'type' => 'Declaration',
                                'label' => $declaration->declaration,
                                'count' => 1,
                                'ulid' => $declaration->ulid ?? null,
                                'color' => 'red'
                            ];
                            $edges[] = [
                                'from' => "manifeste_{$manifesteId}",
                                'to' => "declaration_{$declarationId}",
                                'relation' => 'num_manifeste',
                                'type' => 'has_declaration'
                            ];
                        }
                    }
                }
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

        $manifesteId = $manifeste->ulid ?? $manifeste->instance_id;
        $nodes[] = [
            'id' => "manifeste_{$manifesteId}",
            'type' => 'Manifeste',
            'label' => $manifeste->num_manifeste,
            'count' => 1,
            'ulid' => $manifeste->ulid ?? null,
            'color' => 'grey'
        ];

        // Déclarations liées
        $declarations = DeclarationSg::where('num_manifeste', $manifeste->num_manifeste)->get();
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
            $edges[] = [
                'from' => "manifeste_{$manifesteId}",
                'to' => "declaration_{$declarationId}",
                'relation' => 'num_manifeste',
                'type' => 'has_declaration'
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
