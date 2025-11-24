<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banque;
use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\RechercheAvanceeService;
use App\Support\CacheTagger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechercheController extends Controller
{
    public function __construct(
        private RechercheAvanceeService $rechercheAvanceeService,
        private ExportService $exportService
    ) {}

    /**
     * Recherche avancée unifiée
     * POST /api/recherche/avancee
     */
    public function rechercheAvancee(Request $request): JsonResponse
    {
        $request->validate([
            'option' => 'required|string|in:FDI,Importateur,Manifeste,RFCV,Declaration',
            'valeur' => 'required|string|max:255',
            'filters' => 'sometimes|array',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $option = $request->input('option');
        $valeur = $request->input('valeur');
        $filters = $request->input('filters', []);
        $perPage = $request->integer('per_page', 15);
        $page = $request->integer('page', 1);

        $results = $this->rechercheAvanceeService->searchAdvanced(
            $option,
            $valeur,
            $filters,
            $perPage,
            $page,
            true // Log audit
        );

        $totalResults = array_sum(array_map('count', $results['results']));
        $message = $totalResults > 0
            ? "Recherche \"{$option}\" = \"{$valeur}\" : {$totalResults} résultat(s) trouvé(s) dans tous les onglets"
            : "Aucun résultat trouvé pour \"{$option}\" = \"{$valeur}\"";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $results,
        ]);
    }

    /**
     * Récupère les options de recherche disponibles
     * GET /api/recherche/avancee/options
     */
    public function getSearchOptions(): JsonResponse
    {
        $options = $this->rechercheAvanceeService->getSearchOptions();

        return response()->json([
            'status' => 200,
            'message' => 'Options de recherche récupérées',
            'data' => [
                'options' => $options
            ],
        ]);
    }

    /**
     * Récupère les résultats par onglet
     * GET /api/recherche/avancee/{option}/results
     */
    public function getResultsByTab(Request $request, string $option): JsonResponse
    {
        $request->validate([
            'tab' => 'required|string',
            'valeur' => 'required|string|max:255',
            'filters' => 'sometimes|array',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $valeur = $request->input('valeur');
        $tab = $this->normalizeTab($request->input('tab'), $option);
        $filters = $request->input('filters', []);
        $perPage = $request->integer('per_page', 15);
        $page = $request->integer('page', 1);

        $results = $this->rechercheAvanceeService->getResultsByTab(
            $option,
            $valeur,
            $tab,
            $filters,
            $perPage,
            $page
        );

        return response()->json([
            'status' => 200,
            'message' => 'Résultats récupérés avec succès',
            'data' => $results,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($results),
            ],
        ]);
    }

    /**
     * Génère le diagramme de flux
     * GET /api/recherche/avancee/flow-diagram
     */
    public function getFlowDiagram(Request $request): JsonResponse
    {
        $request->validate([
            'option' => 'required|string|in:FDI,Importateur,Manifeste,RFCV,Declaration',
            'valeur' => 'required|string|max:255',
            'filters' => 'sometimes|array',
        ]);

        $option = $request->input('option');
        $valeur = $request->input('valeur');
        $filters = $request->input('filters', []);

        $results = $this->rechercheAvanceeService->searchAdvanced(
            $option,
            $valeur,
            $filters,
            1,
            1
        );

        return response()->json([
            'status' => 200,
            'message' => 'Diagramme de flux généré',
            'data' => $results['flow_diagram'],
        ]);
    }

    /**
     * Navigation entre entités
     * GET /api/recherche/avancee/navigate
     */
    public function navigate(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|string|in:fdi,fcvr,rfcv,manifeste,declaration',
            'from_ulid' => 'required|string',
            'to' => 'required|string|in:fdi,fcvr,rfcv,manifeste,declaration',
        ]);

        $from = $request->input('from');
        $fromUlid = $request->input('from_ulid');
        $to = $request->input('to');

        $results = $this->rechercheAvanceeService->navigate($from, $fromUlid, $to);

        return response()->json([
            'status' => 200,
            'message' => 'Navigation effectuée',
            'data' => $results,
        ]);
    }

    /**
     * Récupère les détails d'un nœud du workflow (pour modal)
     * GET /api/recherche/avancee/node/{type}/{identifier}
     * Accepte ULID, numero_fdi, id, ou autres identifiants selon le type
     */
    public function getNodeDetails(string $type, string $identifier): JsonResponse
    {
        $cacheKey = "recherche:node:{$type}:" . md5($identifier);
        
        $payload = CacheTagger::tags(['recherche', 'recherche-avancee'])->remember($cacheKey, 300, function () use ($type, $identifier) {
            $details = $this->rechercheAvanceeService->getNodeDetails($type, $identifier);
            
            if (!$details) {
                return null;
            }
            
            return $details;
        });

        if (!$payload) {
            return response()->json([
                'status' => 404,
                'message' => ucfirst($type) . " avec identifiant \"{$identifier}\" introuvable. Essayez avec ULID, numero_fdi, num_rfcv, num_manifeste, ou declaration selon le type.",
                'data' => null,
            ], 404);
        }

        // Log audit
        $actualId = $payload['ulid'] ?? $payload['data']['id'] ?? $identifier;
        AuditService::log('view', "Consultation des détails de {$type} (identifiant: {$identifier})", ucfirst($type), $actualId);

        return response()->json([
            'status' => 200,
            'message' => "Détails de " . ucfirst($type) . " récupérés avec succès",
            'data' => $payload,
        ]);
    }

    /**
     * Export Excel des résultats de recherche avancée
     * POST /api/recherche/avancee/export/excel
     */
    public function exportExcel(Request $request)
    {
        $request->validate([
            'option' => 'required|string|in:FDI,Importateur,Manifeste,RFCV,Declaration',
            'valeur' => 'required|string|max:255',
            'tab' => 'required|string',
            'filters' => 'sometimes|array',
            'filename' => 'nullable|string',
        ]);

        $option = $request->input('option');
        $valeur = $request->input('valeur');
        $tab = $this->normalizeTab($request->input('tab'), $option);
        $filters = $request->input('filters', []);
        $filename = $request->input('filename', "recherche_{$option}_{$tab}_" . date('Y-m-d_His') . '.xlsx');

        // Récupérer les résultats
        $results = $this->rechercheAvanceeService->searchAdvanced($option, $valeur, $filters, 1000, 1, false);
        $data = $results['results'][$tab] ?? [];

        // Convertir les objets Eloquent en tableaux simples
        $data = array_map(function($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            if (is_object($item)) {
                return (array) $item;
            }
            return $item;
        }, $data);

        // Log audit
        AuditService::log('export', "Export Excel de la recherche: {$option} = {$valeur} (onglet: {$tab})", 'RechercheAvancee', null, null, [
            'option' => $option,
            'valeur' => $valeur,
            'tab' => $tab,
            'format' => 'excel',
            'count' => count($data),
        ]);

        return $this->exportService->exportToExcel($data, $filename);
    }

    /**
     * Export PDF des résultats de recherche avancée
     * POST /api/recherche/avancee/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $request->validate([
            'option' => 'required|string|in:FDI,Importateur,Manifeste,RFCV,Declaration',
            'valeur' => 'required|string|max:255',
            'tab' => 'required|string',
            'filters' => 'sometimes|array',
            'template' => 'nullable|string',
        ]);

        $option = $request->input('option');
        $valeur = $request->input('valeur');
        $tab = $this->normalizeTab($request->input('tab'), $option);
        $filters = $request->input('filters', []);
        $template = $request->input('template', 'exports.generic');

        // Récupérer les résultats
        $results = $this->rechercheAvanceeService->searchAdvanced($option, $valeur, $filters, 1000, 1, false);
        $data = $results['results'][$tab] ?? [];

        // Convertir les objets Eloquent en tableaux simples
        $data = array_map(function($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            if (is_object($item)) {
                return (array) $item;
            }
            return $item;
        }, $data);

        // Log audit
        AuditService::log('export', "Export PDF de la recherche: {$option} = {$valeur} (onglet: {$tab})", 'RechercheAvancee', null, null, [
            'option' => $option,
            'valeur' => $valeur,
            'tab' => $tab,
            'format' => 'pdf',
            'count' => count($data),
        ]);

        return $this->exportService->exportToPdf($data, $template);
    }

    /**
     * Export XML des résultats de recherche avancée
     * POST /api/recherche/avancee/export/xml
     */
    public function exportXml(Request $request)
    {
        $request->validate([
            'option' => 'required|string|in:FDI,Importateur,Manifeste,RFCV,Declaration',
            'valeur' => 'required|string|max:255',
            'tab' => 'required|string',
            'filters' => 'sometimes|array',
            'root' => 'nullable|string',
            'filename' => 'nullable|string',
        ]);

        $option = $request->input('option');
        $valeur = $request->input('valeur');
        $tab = $this->normalizeTab($request->input('tab'), $option);
        $filters = $request->input('filters', []);
        $root = $request->input('root', 'items');
        $filename = $request->input('filename', "recherche_{$option}_{$tab}_" . date('Y-m-d_His') . '.xml');

        // Récupérer les résultats
        $results = $this->rechercheAvanceeService->searchAdvanced($option, $valeur, $filters, 1000, 1, false);
        $data = $results['results'][$tab] ?? [];

        // Convertir les objets Eloquent en tableaux simples
        $data = array_map(function($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            if (is_object($item)) {
                return (array) $item;
            }
            return $item;
        }, $data);

        // Log audit
        AuditService::log('export', "Export XML de la recherche: {$option} = {$valeur} (onglet: {$tab})", 'RechercheAvancee', null, null, [
            'option' => $option,
            'valeur' => $valeur,
            'tab' => $tab,
            'format' => 'xml',
            'count' => count($data),
        ]);

        return $this->exportService->exportToXml($data, $root, $filename);
    }

    // Méthodes de recherche simples existantes
    public function rechercheManifeste(Request $request): JsonResponse
    {
        $cacheKey = 'recherche:manifeste:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['recherche', 'manifestes'])->remember($cacheKey, 300, function () use ($request) {
            $query = ManifesteSg::query();

            $this->applyFilters($query, $request, [
                'num_manifeste',
                'num_voyage',
                'nom_moyen_transport',
                'nom_transport',
                'code_bureau',
            ]);

            $perPage = min($request->integer('per_page', 15), 100);
            $results = $query->paginate($perPage);

            $message = $results->total() > 0
                ? "{$results->total()} manifeste(s) trouvé(s)"
                : 'Aucun manifeste trouvé';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ],
            ];
        });

        // Log audit
        AuditService::log('search', "Recherche de manifestes effectuée", 'ManifesteSg');

        return response()->json($payload);
    }

    public function rechercheFdi(Request $request): JsonResponse
    {
        $cacheKey = 'recherche:fdi:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['recherche', 'fdi'])->remember($cacheKey, 300, function () use ($request) {
            $query = FdiSg::query();

            $this->applyFilters($query, $request, [
                'numero_fdi',
                'reference_facture' => 'ref_facture',
                'banque',
                'importateur',
            ]);

            if ($request->filled('date_min')) {
                $query->whereDate('date_fdi', '>=', $request->date('date_min'));
            }

            if ($request->filled('date_max')) {
                $query->whereDate('date_fdi', '<=', $request->date('date_max'));
            }

            $perPage = min($request->integer('per_page', 15), 100);
            $results = $query->paginate($perPage);

            $message = $results->total() > 0
                ? "{$results->total()} FDI trouvée(s)"
                : 'Aucune FDI trouvée';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ],
            ];
        });

        // Log audit
        AuditService::log('search', "Recherche de FDI effectuée", 'FdiSg');

        return response()->json($payload);
    }

    public function rechercheDeclaration(Request $request): JsonResponse
    {
        $cacheKey = 'recherche:declaration:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['recherche', 'declarations'])->remember($cacheKey, 300, function () use ($request) {
            $query = DeclarationSg::query();

            $this->applyFilters($query, $request, [
                'declaration',
                'num_manifeste',
                'num_fdi',
                'bureau',
            ]);

            if ($request->filled('montant_min')) {
                $query->where('valeur_caf_declaration', '>=', $request->float('montant_min'));
            }

            if ($request->filled('montant_max')) {
                $query->where('valeur_caf_declaration', '<=', $request->float('montant_max'));
            }

            $perPage = min($request->integer('per_page', 15), 100);
            $results = $query->paginate($perPage);

            $message = $results->total() > 0
                ? "{$results->total()} déclaration(s) trouvée(s)"
                : 'Aucune déclaration trouvée';

            return [
                'status' => 200,
                'message' => $message,
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ],
            ];
        });

        // Log audit
        AuditService::log('search', "Recherche de déclarations effectuée", 'DeclarationSg');

        return response()->json($payload);
    }

    public function rechercheBanque(Request $request): JsonResponse
    {
        $cacheKey = 'recherche:banque:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['banque', 'recherche'])->remember($cacheKey, 300, function () use ($request) {
            // Utiliser le modèle Banque (table BANQUE) qui contient les données
            $query = Banque::query();

            // Appliquer les filtres
            $filters = [];
            if ($request->filled('num_ddu')) {
                $query->where('REF_DDU', 'like', '%' . $request->get('num_ddu') . '%');
                $filters[] = "num_ddu: {$request->get('num_ddu')}";
            }
            if ($request->filled('num_dvt')) {
                $query->where('NUM_DVT', 'like', '%' . $request->get('num_dvt') . '%');
                $filters[] = "num_dvt: {$request->get('num_dvt')}";
            }
            if ($request->filled('annee_dvt')) {
                $query->where('ANNEE_DVT', $request->get('annee_dvt'));
                $filters[] = "annee_dvt: {$request->get('annee_dvt')}";
            }
            if ($request->filled('date_dvt')) {
                $query->whereDate('DATE_DVT', $request->get('date_dvt'));
                $filters[] = "date_dvt: {$request->get('date_dvt')}";
            }
            if ($request->filled('ref_ddu')) {
                $query->where('REF_DDU', 'like', '%' . $request->get('ref_ddu') . '%');
                $filters[] = "ref_ddu: {$request->get('ref_ddu')}";
            }

            // Tri
            if ($request->filled('sort')) {
                [$column, $direction] = array_pad(explode(',', $request->get('sort')), 2, 'asc');
                $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy('DATE_DVT', 'desc');
            }

            $perPage = min($request->integer('per_page', 15), 100);
            $results = $query->paginate($perPage);

            $filtersText = !empty($filters) ? ' avec filtre(s): ' . implode(', ', $filters) : '';
            $message = $results->total() > 0
                ? "{$results->total()} enregistrement(s) bancaire(s) trouvé(s){$filtersText}"
                : "Aucun enregistrement bancaire trouvé{$filtersText}";

            return [
                'status' => 200,
                'message' => $message,
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    private function applyFilters(Builder $query, Request $request, array $fields): void
    {
        foreach ($fields as $param => $column) {
            if (is_int($param)) {
                $param = $column;
            }

            if ($request->filled($param)) {
                $query->where($column, 'like', '%'.$request->get($param).'%');
            }
        }

        if ($request->filled('sort')) {
            [$column, $direction] = array_pad(explode(',', $request->get('sort')), 2, 'asc');
            $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
        } else {
            $query->latest();
        }
    }

    /**
     * Normalise le nom de l'onglet (accepte formats simplifiés et complets)
     */
    private function normalizeTab(string $tab, string $option): string
    {
        $tab = strtolower(trim($tab));
        $option = strtolower($option);

        // Mapping des formats simplifiés vers les formats complets
        $tabMappings = [
            // Format simplifié -> Format complet
            'fdi' => 'fdi_general',
            'fdi_article' => 'fdi_article',
            'fdi general' => 'fdi_general',
            'fdi article' => 'fdi_article',
            
            'fcvr' => 'fcvr_general',
            'rfcv' => 'fcvr_general',
            'fcvr_article' => 'fcvr_article',
            'rfcv_article' => 'fcvr_article',
            'fcvr general' => 'fcvr_general',
            'rfcv general' => 'fcvr_general',
            'fcvr article' => 'fcvr_article',
            'rfcv article' => 'fcvr_article',
            
            'manifeste' => 'manifeste_general',
            'manifeste_general' => 'manifeste_general',
            'manifeste general' => 'manifeste_general',
            'manifeste_tt' => 'manifeste_tt',
            'manifeste tt' => 'manifeste_tt',
            'manifeste_tc' => 'manifeste_tc',
            'manifeste tc' => 'manifeste_tc',
        ];

        // Si le tab est déjà dans le bon format, le retourner tel quel
        $validTabs = [
            'fdi_general', 'fdi_article',
            'fcvr_general', 'fcvr_article',
            'manifeste_general', 'manifeste_tt', 'manifeste_tc'
        ];

        if (in_array($tab, $validTabs)) {
            return $tab;
        }

        // Essayer le mapping
        if (isset($tabMappings[$tab])) {
            return $tabMappings[$tab];
        }

        // Si l'option est fournie, essayer de deviner le tab par défaut
        if ($option) {
            switch (strtolower($option)) {
                case 'fdi':
                    return 'fdi_general';
                case 'rfcv':
                case 'fcvr':
                    return 'fcvr_general';
                case 'manifeste':
                    return 'manifeste_general';
                case 'declaration':
                    return 'manifeste_general'; // Par défaut pour déclaration
                default:
                    return 'fdi_general'; // Par défaut
            }
        }

        // Par défaut, retourner fdi_general
        return 'fdi_general';
    }
}




