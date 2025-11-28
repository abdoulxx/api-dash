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
use Illuminate\Support\Facades\DB;

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
            'option' => 'required|string|in:FDI,RFCV,Declaration,ManifesteTT,AC,Importateur,Manifeste,Annee,Declarant,BureauPort,PaysExportateur',
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
            'option' => 'required|string|in:FDI,RFCV,Declaration,ManifesteTT,AC,Importateur,Manifeste,Annee,Declarant,BureauPort,PaysExportateur',
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

            // Recherche par numero_manifeste_complet (format: "CODE_BUREAU ANNEE NUMERO")
            if ($request->filled('numero_manifeste_complet')) {
                $numeroComplet = trim($request->input('numero_manifeste_complet'));
                
                // Parser le format "CIAB1 2024 1" ou "CIAB1 2024 1" (avec espaces)
                if (preg_match('/^([A-Z0-9]+)\s+(\d{4})\s+(\d+)$/', $numeroComplet, $matches)) {
                    $codeBureau = $matches[1];
                    $annee = (int) $matches[2];
                    $numeroSeq = $matches[3];
                    
                    // Recherche exacte par composants
                    $query->where('code_bureau', $codeBureau)
                          ->where('annee_manifeste', $annee)
                          ->where(function ($q) use ($numeroSeq) {
                              $q->where('num_man_sydam', $numeroSeq)
                                ->orWhere('instance_id', $numeroSeq);
                          });
                } else {
                    // Si le format ne correspond pas, rechercher dans num_manifeste ou construire la concaténation
                    $query->where(function ($q) use ($numeroComplet) {
                        $q->where('num_manifeste', 'like', "%{$numeroComplet}%")
                          ->orWhereRaw("CONCAT(code_bureau, ' ', annee_manifeste, ' ', COALESCE(num_man_sydam, instance_id)) LIKE ?", ["%{$numeroComplet}%"]);
                    });
                }
            }
            // Recherche "Google-like" : si un paramètre "search" ou "q" est fourni, chercher dans plusieurs champs
            elseif ($request->filled('search') || $request->filled('q')) {
                $searchTerm = $request->input('search') ?? $request->input('q');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('num_manifeste', 'like', "%{$searchTerm}%")
                      ->orWhere('num_voyage', 'like', "%{$searchTerm}%")
                      ->orWhere('nom_moyen_transport', 'like', "%{$searchTerm}%")
                      ->orWhere('nom_transport', 'like', "%{$searchTerm}%")
                      ->orWhere('code_bureau', 'like', "%{$searchTerm}%")
                      ->orWhere('nom_port_charg', 'like', "%{$searchTerm}%")
                      ->orWhere('nom_port_decharg', 'like', "%{$searchTerm}%")
                      ->orWhere('nom_consignataire', 'like', "%{$searchTerm}%")
                      ->orWhere('libelle_bureau', 'like', "%{$searchTerm}%")
                      // Recherche aussi dans le numero_manifeste_complet construit
                      ->orWhereRaw("CONCAT(code_bureau, ' ', annee_manifeste, ' ', COALESCE(num_man_sydam, instance_id)) LIKE ?", ["%{$searchTerm}%"]);
                });
            } else {
                // Filtres spécifiques
                $this->applyFilters($query, $request, [
                    'num_manifeste',
                    'num_voyage',
                    'nom_moyen_transport',
                    'nom_transport',
                    'code_bureau',
                    'nom_port_charg' => 'nom_port_charg',
                    'nom_port_decharg' => 'nom_port_decharg',
                    'nom_consignataire',
                ]);
            }

            // Filtres additionnels
            if ($request->filled('date_arrivee_min')) {
                $query->whereDate('date_arrivee_navire', '>=', $request->date('date_arrivee_min'));
            }

            if ($request->filled('date_arrivee_max')) {
                $query->whereDate('date_arrivee_navire', '<=', $request->date('date_arrivee_max'));
            }

            if ($request->filled('annee_manifeste')) {
                $query->where('annee_manifeste', $request->integer('annee_manifeste'));
            }

            // Tri
            if ($request->filled('sort')) {
                [$column, $direction] = array_pad(explode(',', $request->get('sort')), 2, 'asc');
                $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy('created_at', 'desc')->orderBy('date_arrivee_navire', 'desc')->orderBy('num_manifeste', 'desc');
            }

            $perPage = min($request->integer('per_page', 15), 100);
            $results = $query->paginate($perPage);

            // Enrichir les résultats avec les champs calculés (optimisé pour éviter les redondances)
            $enrichedData = $results->getCollection()->map(function ($manifeste) {
                $numeroComplet = $manifeste->numero_manifeste_complet;
                $identifiant = $manifeste->identifiant;
                $numManifeste = $manifeste->num_manifeste;
                
                // Construire l'objet de base
                $data = [
                    'ulid' => $manifeste->ulid,
                    'instance_id' => $manifeste->instance_id,
                    'numero_manifeste_complet' => $numeroComplet,
                    'num_voyage' => $manifeste->num_voyage,
                    'code_bureau' => $manifeste->code_bureau,
                    'nom_bureau' => $manifeste->nom_bureau,
                    'annee_manifeste' => $manifeste->annee_manifeste,
                    'nom_moyen_transport' => $manifeste->nom_moyen_transport,
                    'nom_transport' => $manifeste->nom_transport,
                    'nom_navire' => $manifeste->nom_navire ?? $manifeste->nom_moyen_transport,
                    'date_arrivee_navire' => $manifeste->date_arrivee_navire,
                    'date_arrivee' => $manifeste->date_arrivee_navire ?? $manifeste->date_arrivee,
                    'nom_port_chargement' => $manifeste->nom_port_charg,
                    'nom_port_dechargement' => $manifeste->nom_port_decharg,
                    'nom_consignataire' => $manifeste->nom_consignataire,
                    'nbre_total_colis' => $manifeste->nbre_total_colis,
                    'total_poids_brut' => $manifeste->total_poids_brut,
                    'nbre_total_conteneur' => $manifeste->nbre_total_conteneur,
                    'created_at' => $manifeste->created_at,
                    'updated_at' => $manifeste->updated_at,
                ];
                
                // Ajouter identifiant seulement s'il est différent de numero_manifeste_complet
                if ($identifiant && $identifiant !== $numeroComplet) {
                    $data['identifiant'] = $identifiant;
                }
                
                // Ajouter num_manifeste seulement s'il est différent de numero_manifeste_complet
                if ($numManifeste && $numManifeste !== $numeroComplet) {
                    $data['num_manifeste'] = $numManifeste;
                }
                
                return $data;
            });

            $totalResults = $results->total();
            $filtersText = $this->buildFiltersText($request);
            $message = $totalResults > 0
                ? "{$totalResults} manifeste(s) trouvé(s){$filtersText}"
                : "Aucun manifeste trouvé{$filtersText}";

            // Construire les métadonnées de pagination (optimisées pour éviter les redondances)
            $currentPage = $results->currentPage();
            $lastPage = $results->lastPage();
            $perPage = $results->perPage();
            $firstItem = $results->firstItem();
            $lastItem = $results->lastItem();
            
            $meta = [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $totalResults,
            ];
            
            // Ajouter last_page seulement s'il y a plus d'une page
            if ($lastPage > 1) {
                $meta['last_page'] = $lastPage;
            }
            
            // Ajouter from/to seulement s'il y a des résultats
            if ($totalResults > 0) {
                $meta['from'] = $firstItem;
                $meta['to'] = $lastItem;
            }
            
            // Construire les liens de pagination (optimisés)
            $links = [];
            
            // Lien first seulement s'il y a plus d'une page et qu'on n'est pas sur la première
            if ($lastPage > 1 && $currentPage > 1) {
                $links['first'] = $results->url(1);
            }
            
            // Lien last seulement s'il y a plus d'une page et qu'on n'est pas sur la dernière
            if ($lastPage > 1 && $currentPage < $lastPage) {
                $links['last'] = $results->url($lastPage);
            }
            
            // Lien prev seulement s'il existe
            if ($results->previousPageUrl()) {
                $links['prev'] = $results->previousPageUrl();
            }
            
            // Lien next seulement s'il existe
            if ($results->nextPageUrl()) {
                $links['next'] = $results->nextPageUrl();
            }

            return [
                'status' => 200,
                'message' => $message,
                'data' => $enrichedData->values()->all(),
                'meta' => $meta,
                'links' => !empty($links) ? $links : null,
            ];
        });

        // Log audit avec détails
        $filtersText = $this->buildFiltersText($request);
        AuditService::log('search', "Recherche de manifestes effectuée{$filtersText}", 'ManifesteSg', null, null, [
            'filters' => $request->except(['per_page', 'page', 'sort']),
            'per_page' => $request->integer('per_page', 15),
            'page' => $request->integer('page', 1),
        ]);

        return response()->json($payload);
    }

    /**
     * Construit un texte descriptif des filtres appliqués
     */
    private function buildFiltersText(Request $request, array $filterMapping = []): string
    {
        $filters = [];
        
        // Si un mapping est fourni, utiliser les clés du mapping
        if (!empty($filterMapping)) {
            foreach ($filterMapping as $key => $label) {
                if ($request->filled($key)) {
                    $value = $request->input($key);
                    $filters[] = "{$label}: \"{$value}\"";
                }
            }
        } else {
            // Comportement par défaut pour les manifestes
            if ($request->filled('search') || $request->filled('q')) {
                $searchTerm = $request->input('search') ?? $request->input('q');
                $filters[] = "recherche: \"{$searchTerm}\"";
            }
            
            if ($request->filled('numero_manifeste_complet')) {
                $filters[] = "numero_manifeste_complet: \"{$request->get('numero_manifeste_complet')}\"";
            }
            
            if ($request->filled('num_manifeste')) {
                $filters[] = "num_manifeste: {$request->get('num_manifeste')}";
            }
            
            if ($request->filled('num_voyage')) {
                $filters[] = "num_voyage: {$request->get('num_voyage')}";
            }
            
            if ($request->filled('code_bureau')) {
                $filters[] = "bureau: {$request->get('code_bureau')}";
            }
            
            if ($request->filled('date_arrivee_min') && $request->filled('date_arrivee_max')) {
                $filters[] = "période: {$request->get('date_arrivee_min')} à {$request->get('date_arrivee_max')}";
            } elseif ($request->filled('date_arrivee_min')) {
                $filters[] = "date_min: {$request->get('date_arrivee_min')}";
            } elseif ($request->filled('date_arrivee_max')) {
                $filters[] = "date_max: {$request->get('date_arrivee_max')}";
            }
            
            if ($request->filled('annee_manifeste')) {
                $filters[] = "année: {$request->get('annee_manifeste')}";
            }
        }

        return !empty($filters) ? implode(', ', $filters) : '';
    }

    /**
     * Recherche simple de FDI
     * GET /api/recherche/fdi
     * 
     * Paramètres :
     * - search : Recherche textuelle "Google-like" (numero_fdi, numero_fdi_complet, importateur, fournisseur, banque, ref_facture)
     * - numero_fdi : Numéro de la FDI
     * - numero_fdi_complet : Numéro FDI complet (format: ANNEE || BUREAU || SERIE_FDI || NUMERO_SERIE)
     * - reference_facture : Référence facture
     * - banque : Nom de la banque
     * - importateur : Nom de l'importateur
     * - date_min : Date minimale (format: YYYY-MM-DD)
     * - date_max : Date maximale (format: YYYY-MM-DD)
     * - sort : Tri (ex: date_fdi,desc)
     * - per_page : Nombre de résultats par page (défaut: 15, max: 100)
     * - page : Numéro de page (défaut: 1)
     */
    public function rechercheFdi(Request $request): JsonResponse
    {
        $cacheKey = 'recherche:fdi:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['recherche', 'fdi'])->remember($cacheKey, 300, function () use ($request) {
            $query = FdiSg::query();

            // Recherche "Google-like" sur plusieurs champs
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('numero_fdi', 'like', "%{$search}%")
                      ->orWhere('importateur', 'like', "%{$search}%")
                      ->orWhere('fournisseur', 'like', "%{$search}%")
                      ->orWhere('banque', 'like', "%{$search}%")
                  ->orWhere('ref_facture', 'like', "%{$search}%")
                  ->orWhere('cc', 'like', "%{$search}%");
                
                // Recherche dans le numero_fdi_complet construit (convertir les entiers en text pour PostgreSQL)
                $driver = DB::getDriverName();
                if ($driver === 'pgsql') {
                    $q->orWhereRaw("CONCAT(COALESCE(CAST(annee AS TEXT), ''), COALESCE(bureau, ''), COALESCE(serie_fdi, ''), COALESCE(CAST(numero_serie AS TEXT), '')) LIKE ?", ["%{$search}%"]);
                } else {
                    $q->orWhereRaw("CONCAT(COALESCE(annee, ''), COALESCE(bureau, ''), COALESCE(serie_fdi, ''), COALESCE(numero_serie, '')) LIKE ?", ["%{$search}%"]);
                }
                
                // Si la valeur correspond au format "ANNEEBUREAUSERIENUMERO", recherche exacte
                if (preg_match('/^(\d{4})([A-Z0-9]+)([A-Z])(\d+)$/', trim($search), $matches)) {
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
            });
            }

            // Filtres spécifiques
            if ($request->filled('numero_fdi')) {
                $query->where('numero_fdi', 'like', "%{$request->input('numero_fdi')}%");
            }

            if ($request->filled('numero_fdi_complet')) {
                $numeroComplet = $request->input('numero_fdi_complet');
                // Recherche par numéro complet (format: ANNEE || BUREAU || SERIE_FDI || NUMERO_SERIE)
                if (preg_match('/^(\d{4})([A-Z0-9]+)([A-Z])(\d+)$/', trim($numeroComplet), $matches)) {
                    $annee = (int) $matches[1];
                    $bureau = $matches[2];
                    $serie = $matches[3];
                    $numero = $matches[4];
                    
                    $query->where(function ($q) use ($annee, $bureau, $serie, $numero) {
                        $q->where('annee', $annee)
                          ->where('bureau', $bureau)
                          ->where('serie_fdi', $serie)
                          ->where('numero_serie', $numero);
                    });
                } else {
                    // Recherche partielle dans le numéro complet construit (convertir les entiers en text pour PostgreSQL)
                    $driver = DB::getDriverName();
                    if ($driver === 'pgsql') {
                        $query->whereRaw("CONCAT(COALESCE(CAST(annee AS TEXT), ''), COALESCE(bureau, ''), COALESCE(serie_fdi, ''), COALESCE(CAST(numero_serie AS TEXT), '')) LIKE ?", ["%{$numeroComplet}%"]);
                    } else {
                        $query->whereRaw("CONCAT(COALESCE(annee, ''), COALESCE(bureau, ''), COALESCE(serie_fdi, ''), COALESCE(numero_serie, '')) LIKE ?", ["%{$numeroComplet}%"]);
                    }
                }
            }

            if ($request->filled('reference_facture')) {
                $query->where('ref_facture', 'like', "%{$request->input('reference_facture')}%");
            }

            if ($request->filled('banque')) {
                $query->where('banque', 'like', "%{$request->input('banque')}%");
            }

            if ($request->filled('importateur')) {
                $query->where('importateur', 'like', "%{$request->input('importateur')}%");
            }

            if ($request->filled('date_min')) {
                $query->whereDate('date_fdi', '>=', $request->date('date_min'));
            }

            if ($request->filled('date_max')) {
                $query->whereDate('date_fdi', '<=', $request->date('date_max'));
            }

            // Tri
            if ($request->filled('sort')) {
                $sortParts = explode(',', $request->input('sort'));
                $sortField = $sortParts[0] ?? 'date_fdi';
                $sortDirection = strtolower($sortParts[1] ?? 'desc');
                
                // Mapping des champs de tri
                $sortMapping = [
                    'date_fdi' => 'date_fdi',
                    'numero_fdi' => 'numero_fdi',
                    'importateur' => 'importateur',
                    'banque' => 'banque',
                    'valeur_caf' => 'valeur_caf',
                    'valeur_fob_cfa' => 'valeur_fob_cfa',
                ];
                
                $sortField = $sortMapping[$sortField] ?? 'date_fdi';
                $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';
                
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('date_fdi', 'desc');
            }

            $perPage = min($request->integer('per_page', 15), 100);
            $page = $request->integer('page', 1);
            $results = $query->paginate($perPage, ['*'], 'page', $page);

            // Construire le message avec les filtres appliqués
            $filtersText = $this->buildFiltersText($request, [
                'search' => 'recherche textuelle',
                'numero_fdi' => 'numero_fdi',
                'numero_fdi_complet' => 'numero_fdi_complet',
                'reference_facture' => 'reference_facture',
                'banque' => 'banque',
                'importateur' => 'importateur',
                'date_min' => 'date_min',
                'date_max' => 'date_max',
            ]);

            $message = $results->total() > 0
                ? "{$results->total()} FDI trouvée(s)" . ($filtersText ? " avec filtre(s): {$filtersText}" : '')
                : 'Aucune FDI trouvée' . ($filtersText ? " avec filtre(s): {$filtersText}" : '');

            // Enrichir les données avec numero_fdi_complet et identifiant
            $data = $results->map(function ($fdi) {
                return array_merge($fdi->toArray(), [
                    'numero_fdi_complet' => $fdi->numero_fdi_complet,
                    'identifiant' => $fdi->identifiant,
                ]);
            })->toArray();

            return [
                'status' => 200,
                'message' => $message,
                'data' => $data,
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                ],
                'links' => [
                    'first' => $results->url(1),
                    'last' => $results->url($results->lastPage()),
                    'prev' => $results->previousPageUrl(),
                    'next' => $results->nextPageUrl(),
                ],
            ];
        });

        // Log audit
        $filtersText = $this->buildFiltersText($request, [
            'search' => 'recherche textuelle',
            'numero_fdi' => 'numero_fdi',
            'numero_fdi_complet' => 'numero_fdi_complet',
        ]);
        AuditService::log('search', "Recherche de FDI effectuée" . ($filtersText ? " ({$filtersText})" : ''), 'FdiSg');

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















