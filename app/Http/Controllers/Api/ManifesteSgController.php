<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManifesteSgRequest;
use App\Http\Requests\UpdateManifesteSgRequest;
use App\Models\DeclarationSg;
use App\Models\ManifesteSg;
use App\Models\ManifesteTc;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Manifestes SG",
 *     description="Gestion des manifestes du segment SG."
 * )
 */
class ManifesteSgController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/manifestes/sg",
     *     summary="Liste des manifestes SG",
     *     operationId="listManifestesSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="code_bureau",
     *         in="query",
     *         description="Filtrer par code bureau",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="num_manifeste",
     *         in="query",
     *         description="Filtrer par numero de manifeste (recherche partielle)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'elements par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginee des manifestes",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'manifestes:index:' . md5($request->fullUrl());

        $payload = CacheTagger::tags(['manifestes', 'manifeste_sg'])->remember($cacheKey, 300, function () use ($request) {
            $query = ManifesteSg::query();

            // Recherche textuelle sur plusieurs champs (Google-like)
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('num_manifeste', 'like', "%{$search}%")
                      ->orWhere('code_bureau', 'like', "%{$search}%")
                      ->orWhere('libelle_bureau', 'like', "%{$search}%")
                      ->orWhere('num_voyage', 'like', "%{$search}%")
                      ->orWhere('nom_moyen_transport', 'like', "%{$search}%")
                      ->orWhere('nom_consignataire', 'like', "%{$search}%")
                      ->orWhere('code_consignataire', 'like', "%{$search}%")
                      ->orWhere('nom_port_charg', 'like', "%{$search}%")
                      ->orWhere('nom_port_decharg', 'like', "%{$search}%")
                      ->orWhereRaw("CAST(instance_id AS TEXT) LIKE ?", ["%{$search}%"])
                      ->orWhereRaw("CAST(num_man_sydam AS TEXT) LIKE ?", ["%{$search}%"])
                      // Recherche sur le numéro complet construit (PostgreSQL: ||, MySQL: CONCAT)
                      ->orWhereRaw("(code_bureau || ' ' || CAST(annee_manifeste AS TEXT) || ' ' || COALESCE(CAST(num_man_sydam AS TEXT), CAST(instance_id AS TEXT))) LIKE ?", ["%{$search}%"]);
                });
            }

            // Filtres spécifiques
            if ($request->filled('code_bureau')) {
                $query->where('code_bureau', $request->get('code_bureau'));
            }

            if ($request->filled('num_manifeste')) {
                $query->where('num_manifeste', 'like', '%'.$request->get('num_manifeste').'%');
            }

            if ($request->filled('annee')) {
                $query->where('annee_manifeste', $request->integer('annee'));
            }

            if ($request->filled('date_debut')) {
                $query->where('date_manifeste', '>=', $request->get('date_debut'));
            }

            if ($request->filled('date_fin')) {
                $query->where('date_manifeste', '<=', $request->get('date_fin'));
            }

            if ($request->filled('mode_transport')) {
                $query->where('code_transport', $request->get('mode_transport'));
            }

            $perPage = $request->integer('per_page', 15);
            $manifestes = $query->orderBy('date_manifeste', 'desc')
                                ->orderBy('num_manifeste', 'desc')
                                ->paginate($perPage);

            // Enrichir chaque manifeste avec numero_manifeste_complet, identifiant et message_resume
            $enrichedData = $manifestes->getCollection()->map(function ($manifeste) {
                $data = $manifeste->toArray();
                
                // Ajouter message_resume
                $data['message_resume'] = sprintf(
                    '%s | Bureau : %s | Voyage : %s | Navire : %s | Colis : %s | Poids : %s kg',
                    $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? 'N/A',
                    $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A',
                    $manifeste->num_voyage ?? 'N/A',
                    $manifeste->nom_moyen_transport ?? 'N/A',
                    number_format((float) ($manifeste->nbre_total_colis ?? 0), 0, ',', ' '),
                    number_format((float) ($manifeste->total_poids_brut ?? 0), 0, ',', ' ')
                );

                return $data;
            })->toArray();

            // Message adapté selon les résultats
            $total = $manifestes->total();
            $message = '';
            
            if ($total > 0) {
                if ($request->filled('search')) {
                    $message = "{$total} manifeste(s) trouvé(s) pour la recherche \"{$request->get('search')}\"";
                } else {
                    $message = "{$total} manifeste(s) récupéré(s) avec succès";
                }
            } else {
                if ($request->filled('search')) {
                    $message = "Aucun manifeste trouvé pour la recherche \"{$request->get('search')}\"";
                } else {
                    $message = "Aucun manifeste trouvé";
                }
            }

            return [
                'status' => 200,
                'message' => $message,
                'data' => $enrichedData,
                'meta' => [
                    'current_page' => $manifestes->currentPage(),
                    'last_page' => $manifestes->lastPage(),
                    'per_page' => $manifestes->perPage(),
                    'total' => $total,
                    'from' => $manifestes->firstItem(),
                    'to' => $manifestes->lastItem(),
                ],
                'links' => [
                    'first' => $manifestes->url(1) ? str_replace(request()->root(), '', $manifestes->url(1)) : null,
                    'last' => $manifestes->url($manifestes->lastPage()) ? str_replace(request()->root(), '', $manifestes->url($manifestes->lastPage())) : null,
                    'prev' => $manifestes->previousPageUrl() ? str_replace(request()->root(), '', $manifestes->previousPageUrl()) : null,
                    'next' => $manifestes->nextPageUrl() ? str_replace(request()->root(), '', $manifestes->nextPageUrl()) : null,
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/manifestes/sg",
     *     summary="Creer un manifeste SG",
     *     operationId="storeManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"instance_id","num_manifeste"},
     *             @OA\Property(property="instance_id", type="integer"),
     *             @OA\Property(property="num_manifeste", type="string"),
     *             @OA\Property(property="code_bureau", type="string"),
     *             @OA\Property(property="date_arrivee", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Manifeste cree",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(StoreManifesteSgRequest $request): JsonResponse
    {
        $payload = $request->validated();

        // Générer instance_id si non fourni
        if (empty($payload['instance_id'])) {
            $payload['instance_id'] = (ManifesteSg::max('instance_id') ?? 0) + 1;
        }

        // Générer num_man_sydam si non fourni (générer un numéro séquentiel pour l'année et le bureau)
        if (empty($payload['num_man_sydam']) && !empty($payload['annee_manifeste']) && !empty($payload['code_bureau'])) {
            $maxNumManSydam = ManifesteSg::where('annee_manifeste', $payload['annee_manifeste'])
                ->where('code_bureau', $payload['code_bureau'])
                ->max('num_man_sydam');
            $payload['num_man_sydam'] = $maxNumManSydam ? ((int) $maxNumManSydam + 1) : 1;
        }

        // Générer num_manifeste automatiquement si non fourni mais que les composants sont présents
        if (empty($payload['num_manifeste']) && !empty($payload['code_bureau']) && !empty($payload['annee_manifeste'])) {
            $numeroSequential = $payload['num_man_sydam'] ?? $payload['instance_id'];
            $payload['num_manifeste'] = sprintf('%s %d %s', $payload['code_bureau'], $payload['annee_manifeste'], $numeroSequential);
        }

        // Générer ULID si non fourni
        if (empty($payload['ulid'])) {
            $payload['ulid'] = (string) Str::ulid();
        }

        $manifeste = ManifesteSg::create($payload);
        $manifeste->refresh();

        // Log the creation
        $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
        AuditService::log('create', "Le manifeste \"{$numero}\" a été créé", 'ManifesteSg', $manifeste->instance_id, null, $manifeste->toArray());

        // Invalider le cache
        CacheTagger::tags(['manifestes', 'manifeste_sg'])->flush();

        // Enrichir la réponse
        $data = $manifeste->toArray();
        $data['numero_manifeste_complet'] = $manifeste->numero_manifeste_complet;
        $data['identifiant'] = $manifeste->identifiant;
        $data['message_resume'] = sprintf(
            '%s | Bureau : %s | Voyage : %s | Navire : %s | Colis : %s | Poids : %s kg',
            $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? 'N/A',
            $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A',
            $manifeste->num_voyage ?? 'N/A',
            $manifeste->nom_moyen_transport ?? 'N/A',
            number_format((float) ($manifeste->nbre_total_colis ?? 0), 0, ',', ' '),
            number_format((float) ($manifeste->total_poids_brut ?? 0), 0, ',', ' ')
        );

        return response()->json([
            'status' => 201,
            'message' => sprintf(
                'Le manifeste "%s" a été créé avec succès | Bureau : %s | Voyage : %s',
                $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? 'N/A',
                $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A',
                $manifeste->num_voyage ?? 'N/A'
            ),
            'data' => $data,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}",
     *     summary="Afficher un manifeste SG",
     *     operationId="showManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         description="Identifiant (id ou instance_id) du manifeste",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Manifeste trouve",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Manifeste introuvable")
     * )
     */
    public function show(string $manifesteId): JsonResponse
    {
        $cacheKey = "manifestes:show:{$manifesteId}";

        $payload = CacheTagger::tags(['manifestes', 'manifeste_sg'])->remember($cacheKey, 300, function () use ($manifesteId) {
            // Récupérer le manifeste par ULID ou ID
            $manifeste = null;
            if (Str::isUlid($manifesteId)) {
                $manifeste = ManifesteSg::where('ulid', $manifesteId)->first();
            } elseif (ctype_digit($manifesteId)) {
                $manifeste = ManifesteSg::where('instance_id', (int) $manifesteId)->first();
            } else {
                $manifeste = ManifesteSg::where('num_manifeste', $manifesteId)->first();
            }

            if (!$manifeste) {
                throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$manifesteId]);
            }

            // Charger les relations
            $manifeste->load([
                'titresTransport' => function ($query) {
                    $query->limit(10); // Limiter pour éviter les réponses trop lourdes
                },
                'conteneurs' => function ($query) {
                    $query->limit(10); // Limiter pour éviter les réponses trop lourdes
                },
                'declarations' => function ($query) {
                    $query->limit(10); // Limiter pour éviter les réponses trop lourdes
                }
            ]);

            // Compter les totaux
            $titresTransportCount = $manifeste->titresTransport()->count();
            $conteneursCount = $manifeste->conteneurs()->count();
            $declarationsCount = $manifeste->declarations()->count();

            // Préparer les données de base
            $data = $manifeste->toArray();
            $data['numero_manifeste_complet'] = $manifeste->numero_manifeste_complet;
            $data['identifiant'] = $manifeste->identifiant;
            $data['message_resume'] = sprintf(
                '%s | Bureau : %s | Voyage : %s | Navire : %s | BL : %d | Colis : %s | Conteneurs : %d | Poids : %s kg',
                $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? 'N/A',
                $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A',
                $manifeste->num_voyage ?? 'N/A',
                $manifeste->nom_moyen_transport ?? 'N/A',
                $titresTransportCount,
                number_format((float) ($manifeste->nbre_total_colis ?? 0), 0, ',', ' '),
                $conteneursCount,
                number_format((float) ($manifeste->total_poids_brut ?? 0), 0, ',', ' ')
            );

            // Préparer les relations avec identifiants
            $relations = [];

            // Titres de transport (avec données réelles)
            $titresTransport = $manifeste->titresTransport()->limit(10)->get()->map(function ($tt) {
                return [
                    'id' => $tt->id ?? $tt->instance_id ?? null,
                    'num_titre_transport' => $tt->num_titre_transport ?? null,
                    'ligne_manifeste' => $tt->ligne_manfeste ?? null, // Note: 'ligne_manfeste' avec 'f' dans la table
                    'nom_exportateur' => $tt->nom_exportateur ?? null,
                    'nom_importateur' => $tt->nom_importateur ?? null,
                    'nbr_colis' => $tt->nbr_colis ?? null,
                    'poids_brut' => $tt->poids_brut ?? null,
                    'nombre_conteneur' => $tt->nombre_conteneur ?? null,
                ];
            })->toArray();

            $relations['titres_transport'] = [
                'count' => $titresTransportCount,
                'has_titres_transport' => $titresTransportCount > 0,
                'url' => "/api/manifestes/sg/{$manifesteId}/titres-transport",
                'items' => $titresTransport,
                'message' => $titresTransportCount > 0 
                    ? "{$titresTransportCount} titre(s) de transport associé(s) à ce manifeste" 
                    : "Aucun titre de transport associé à ce manifeste",
            ];

            // Conteneurs (avec données réelles)
            $conteneurs = $manifeste->conteneurs()->limit(10)->get()->map(function ($tc) {
                return [
                    'id' => $tc->id ?? $tc->instance_id ?? null,
                    'num_conteneur' => $tc->num_conteneur ?? null,
                    'num_titre_transport' => $tc->num_titre_transport ?? null,
                    'taille_conteneur' => $tc->taille_conteneur ?? null,
                    'type_conteneur' => $tc->type_conteneur ?? null,
                    'nature_conteneur' => $tc->nature_conteneur ?? null,
                    'poids_brut' => $tc->poids_brut ?? null,
                    'poids_net' => $tc->poids_net ?? null,
                ];
            })->toArray();

            $relations['conteneurs'] = [
                'count' => $conteneursCount,
                'has_conteneurs' => $conteneursCount > 0,
                'url' => "/api/manifestes/sg/{$manifesteId}/conteneurs",
                'items' => $conteneurs,
                'message' => $conteneursCount > 0 
                    ? "{$conteneursCount} conteneur(s) associé(s) à ce manifeste" 
                    : "Aucun conteneur associé à ce manifeste",
            ];

            // Déclarations (résumé)
            $declarations = $manifeste->declarations()->limit(10)->get()->map(function ($declaration) {
                return [
                    'ulid' => $declaration->ulid ?? null,
                    'declaration' => $declaration->declaration ?? null,
                    'numero_declaration_complet' => $declaration->numero_declaration_complet ?? null,
                    'identifiant' => $declaration->identifiant ?? null,
                    'date_declaration' => $declaration->date_declaration ?? null,
                    'importateur' => $declaration->importateur ?? null,
                ];
            })->toArray();

            $relations['declarations'] = [
                'count' => $declarationsCount,
                'has_declarations' => $declarationsCount > 0,
                'url' => "/api/manifestes/sg/{$manifesteId}/declarations",
                'items' => $declarations,
                'message' => $declarationsCount > 0 
                    ? "{$declarationsCount} déclaration(s) associée(s) à ce manifeste" 
                    : "Aucune déclaration associée à ce manifeste",
            ];

            $data['relations'] = $relations;
            $data['titres_transport_count'] = $titresTransportCount;
            $data['conteneurs_count'] = $conteneursCount;
            $data['declarations_count'] = $declarationsCount;
            $data['has_titres_transport'] = $titresTransportCount > 0;
            $data['has_conteneurs'] = $conteneursCount > 0;
            $data['has_declarations'] = $declarationsCount > 0;

            // Ajouter les titres de transport et conteneurs directement dans data
            $data['titres_transport'] = $titresTransport;
            $data['conteneurs'] = $conteneurs;

            // Message enrichi
            $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
            $message = sprintf(
                "Détails du manifeste \"%s\" récupérés avec succès | Bureau : %s | Voyage : %s | %d BL | %d conteneur(s) | %d déclaration(s)",
                $numero,
                $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A',
                $manifeste->num_voyage ?? 'N/A',
                $titresTransportCount,
                $conteneursCount,
                $declarationsCount
            );

            return [
                'status' => 200,
                'message' => $message,
                'data' => $data,
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Put(
     *     path="/api/manifestes/sg/{manifeste}",
     *     summary="Mettre a jour un manifeste SG",
     *     operationId="updateManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Manifeste mis a jour",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Manifeste introuvable")
     * )
     */
    public function update(UpdateManifesteSgRequest $request, string $manifesteId): JsonResponse
    {
        // Récupérer le manifeste par ULID ou ID
        $manifeste = null;
        if (Str::isUlid($manifesteId)) {
            $manifeste = ManifesteSg::where('ulid', $manifesteId)->first();
        } elseif (ctype_digit($manifesteId)) {
            $manifeste = ManifesteSg::where('instance_id', (int) $manifesteId)->first();
        } else {
            $manifeste = ManifesteSg::where('num_manifeste', $manifesteId)->first();
        }

        if (!$manifeste) {
            throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$manifesteId]);
        }

        $oldValues = $manifeste->toArray();
        $payload = $request->validated();

        // Si num_man_sydam est modifié et que num_manifeste n'est pas fourni, régénérer num_manifeste
        if (isset($payload['num_man_sydam']) && !isset($payload['num_manifeste']) && 
            !empty($payload['code_bureau']) && !empty($payload['annee_manifeste'])) {
            $payload['num_manifeste'] = sprintf('%s %d %s', 
                $payload['code_bureau'] ?? $manifeste->code_bureau,
                $payload['annee_manifeste'] ?? $manifeste->annee_manifeste,
                $payload['num_man_sydam']
            );
        } elseif (isset($payload['code_bureau']) || isset($payload['annee_manifeste']) || isset($payload['num_man_sydam'])) {
            // Si les composants sont modifiés mais pas num_manifeste, régénérer
            if (!isset($payload['num_manifeste'])) {
                $codeBureau = $payload['code_bureau'] ?? $manifeste->code_bureau;
                $annee = $payload['annee_manifeste'] ?? $manifeste->annee_manifeste;
                $numManSydam = $payload['num_man_sydam'] ?? $manifeste->num_man_sydam;
                
                if ($codeBureau && $annee && $numManSydam) {
                    $payload['num_manifeste'] = sprintf('%s %d %s', $codeBureau, $annee, $numManSydam);
                }
            }
        }

        // Mettre à jour le manifeste
        $manifeste->update($payload);
        $manifeste->refresh();

        // Identifier les champs modifiés
        $modifiedFields = [];
        foreach ($payload as $key => $value) {
            if (isset($oldValues[$key]) && $oldValues[$key] != $value) {
                $modifiedFields[] = $key;
            } elseif (!isset($oldValues[$key]) && $value !== null) {
                $modifiedFields[] = $key;
            }
        }

        // Log the update
        $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
        $bureau = $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A';
        $voyage = $manifeste->num_voyage ?? 'N/A';
        
        $modifiedFieldsStr = !empty($modifiedFields) 
            ? ' | Champs modifiés : ' . implode(', ', $modifiedFields)
            : '';
        
        AuditService::log(
            'update', 
            "Le manifeste \"{$numero}\" a été modifié | Bureau : {$bureau} | Voyage : {$voyage}{$modifiedFieldsStr}",
            'ManifesteSg', 
            $manifeste->instance_id, 
            $oldValues, 
            $manifeste->toArray()
        );

        // Invalider le cache
        CacheTagger::tags(['manifestes', 'manifeste_sg'])->flush();

        // Enrichir la réponse
        $data = $manifeste->toArray();
        $data['numero_manifeste_complet'] = $manifeste->numero_manifeste_complet;
        $data['identifiant'] = $manifeste->identifiant;
        $data['message_resume'] = sprintf(
            '%s | Bureau : %s | Voyage : %s | Navire : %s | Colis : %s | Poids : %s kg',
            $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? 'N/A',
            $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A',
            $manifeste->num_voyage ?? 'N/A',
            $manifeste->nom_moyen_transport ?? 'N/A',
            number_format((float) ($manifeste->nbre_total_colis ?? 0), 0, ',', ' '),
            number_format((float) ($manifeste->total_poids_brut ?? 0), 0, ',', ' ')
        );

        $message = sprintf(
            'Le manifeste "%s" a été modifié avec succès | Bureau : %s | Voyage : %s',
            $numero,
            $bureau,
            $voyage
        );

        if (!empty($modifiedFields)) {
            $message .= ' | Champs modifiés : ' . implode(', ', $modifiedFields);
        }

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/manifestes/sg/{manifeste}",
     *     summary="Supprimer un manifeste SG",
     *     operationId="deleteManifesteSg",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=204, description="Manifeste supprime"),
     *     @OA\Response(response=404, description="Manifeste introuvable")
     * )
     */
    public function destroy(string $manifesteId): JsonResponse
    {
        // Récupérer le manifeste par ULID ou ID
        $manifeste = null;
        if (Str::isUlid($manifesteId)) {
            $manifeste = ManifesteSg::where('ulid', $manifesteId)->first();
        } elseif (ctype_digit($manifesteId)) {
            $manifeste = ManifesteSg::where('instance_id', (int) $manifesteId)->first();
        } else {
            $manifeste = ManifesteSg::where('num_manifeste', $manifesteId)->first();
        }

        if (!$manifeste) {
            throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$manifesteId]);
        }

        // Récupérer les informations avant suppression
        $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
        $bureau = $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A';
        $voyage = $manifeste->num_voyage ?? 'N/A';
        
        // Compter les relations avant suppression
        $titresTransportCount = $manifeste->titresTransport()->count();
        $conteneursCount = $manifeste->conteneurs()->count();
        $declarationsCount = $manifeste->declarations()->count();
        
        $oldValues = $manifeste->toArray();
        
        // Soft delete
        $manifeste->delete();

        // Log the deletion
        AuditService::log(
            'delete', 
            "Le manifeste \"{$numero}\" a été supprimé (soft delete) | Bureau : {$bureau} | Voyage : {$voyage} | {$titresTransportCount} BL | {$conteneursCount} conteneur(s) | {$declarationsCount} déclaration(s)",
            'ManifesteSg', 
            $manifeste->instance_id, 
            $oldValues, 
            null
        );

        // Invalider le cache
        CacheTagger::tags(['manifestes', 'manifeste_sg'])->flush();

        // Message enrichi avec informations contextuelles
        $message = sprintf(
            'Le manifeste "%s" a été supprimé avec succès (soft delete) | Bureau : %s | Voyage : %s | %d BL | %d conteneur(s) | %d déclaration(s)',
            $numero,
            $bureau,
            $voyage,
            $titresTransportCount,
            $conteneursCount,
            $declarationsCount
        );

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => null,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}/titres-transport",
     *     summary="Lister les titres de transport d'un manifeste",
     *     operationId="manifesteTitresTransport",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des titres de transport",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function titresTransport(string $manifesteId, Request $request): JsonResponse
    {
        $cacheKey = "manifestes:titres-transport:{$manifesteId}:" . md5($request->fullUrl());

        $payload = CacheTagger::tags(['manifestes', 'manifeste_sg', 'manifeste_tt'])->remember($cacheKey, 300, function () use ($manifesteId, $request) {
            // Récupérer le manifeste par ULID ou ID
            $manifeste = null;
            if (Str::isUlid($manifesteId)) {
                $manifeste = ManifesteSg::where('ulid', $manifesteId)->first();
            } elseif (ctype_digit($manifesteId)) {
                $manifeste = ManifesteSg::where('instance_id', (int) $manifesteId)->first();
            } else {
                $manifeste = ManifesteSg::where('num_manifeste', $manifesteId)->first();
            }

            if (!$manifeste) {
                throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$manifesteId]);
            }

            $perPage = min($request->integer('per_page', 25), 100);
            $titresTransport = $manifeste->titresTransport()
                ->orderBy('ligne_manfeste', 'asc')
                ->orderBy('num_titre_transport', 'asc')
                ->paginate($perPage);

            $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
            $bureau = $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A';
            $voyage = $manifeste->num_voyage ?? 'N/A';

            // Enrichir chaque titre de transport avec les identifiants du manifeste
            $items = collect($titresTransport->items())->map(function ($tt) use ($manifeste) {
                $ttArray = is_array($tt) ? $tt : $tt->toArray();
                $ttArray['manifeste'] = [
                    'ulid' => $manifeste->ulid ?? null,
                    'identifiant' => $manifeste->identifiant ?? null,
                    'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                    'num_manifeste' => $manifeste->num_manifeste ?? null,
                    'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                    'code_bureau' => $manifeste->code_bureau ?? null,
                    'nom_bureau' => $manifeste->libelle_bureau ?? null,
                    'num_voyage' => $manifeste->num_voyage ?? null,
                    'date_manifeste' => $manifeste->date_manifeste ?? null,
                ];
                return $ttArray;
            })->toArray();

            $message = $titresTransport->total() > 0
                ? sprintf(
                    '%d titre(s) de transport trouvé(s) pour le manifeste "%s" | Bureau : %s | Voyage : %s',
                    $titresTransport->total(),
                    $numero,
                    $bureau,
                    $voyage
                )
                : sprintf(
                    'Aucun titre de transport trouvé pour le manifeste "%s" | Bureau : %s | Voyage : %s',
                    $numero,
                    $bureau,
                    $voyage
                );

            // Informations du manifeste pour le contexte
            $manifesteInfo = [
                'ulid' => $manifeste->ulid ?? null,
                'identifiant' => $manifeste->identifiant ?? null,
                'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                'num_manifeste' => $manifeste->num_manifeste ?? null,
                'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                'code_bureau' => $manifeste->code_bureau ?? null,
                'libelle_bureau' => $manifeste->libelle_bureau ?? null,
                'num_voyage' => $manifeste->num_voyage ?? null,
                'date_manifeste' => $manifeste->date_manifeste ?? null,
                'nom_moyen_transport' => $manifeste->nom_moyen_transport ?? null,
                'nbre_total_bl' => $manifeste->nbre_total_bl ?? null,
            ];

            return [
                'status' => 200,
                'message' => $message,
                'data' => $items,
                'manifeste' => $manifesteInfo,
                'meta' => [
                    'current_page' => $titresTransport->currentPage(),
                    'last_page' => $titresTransport->lastPage(),
                    'per_page' => $titresTransport->perPage(),
                    'total' => $titresTransport->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}/conteneurs",
     *     summary="Lister les conteneurs d'un manifeste",
     *     operationId="manifesteConteneurs",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des conteneurs",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function conteneurs(string $manifesteId, Request $request): JsonResponse
    {
        $cacheKey = "manifestes:conteneurs:{$manifesteId}:" . md5($request->fullUrl());

        $payload = CacheTagger::tags(['manifestes', 'manifeste_sg', 'manifeste_tc'])->remember($cacheKey, 300, function () use ($manifesteId, $request) {
            // Récupérer le manifeste par ULID ou ID
            $manifeste = null;
            if (Str::isUlid($manifesteId)) {
                $manifeste = ManifesteSg::where('ulid', $manifesteId)->first();
            } elseif (ctype_digit($manifesteId)) {
                $manifeste = ManifesteSg::where('instance_id', (int) $manifesteId)->first();
            } else {
                $manifeste = ManifesteSg::where('num_manifeste', $manifesteId)->first();
            }

            if (!$manifeste) {
                throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$manifesteId]);
            }

            $perPage = min($request->integer('per_page', 25), 100);
            $conteneurs = $manifeste->conteneurs()
                ->orderBy('numero_bl', 'asc')
                ->orderBy('num_conteneur', 'asc')
                ->paginate($perPage);

            $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
            $bureau = $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A';
            $voyage = $manifeste->num_voyage ?? 'N/A';

            // Enrichir chaque conteneur avec les identifiants du manifeste
            $items = collect($conteneurs->items())->map(function ($conteneur) use ($manifeste) {
                $conteneurArray = is_array($conteneur) ? $conteneur : $conteneur->toArray();
                $conteneurArray['manifeste'] = [
                    'ulid' => $manifeste->ulid ?? null,
                    'identifiant' => $manifeste->identifiant ?? null,
                    'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                    'num_manifeste' => $manifeste->num_manifeste ?? null,
                    'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                    'code_bureau' => $manifeste->code_bureau ?? null,
                    'nom_bureau' => $manifeste->libelle_bureau ?? null,
                    'num_voyage' => $manifeste->num_voyage ?? null,
                    'date_manifeste' => $manifeste->date_manifeste ?? null,
                ];
                return $conteneurArray;
            })->toArray();

            $message = $conteneurs->total() > 0
                ? sprintf(
                    '%d conteneur(s) trouvé(s) pour le manifeste "%s" | Bureau : %s | Voyage : %s',
                    $conteneurs->total(),
                    $numero,
                    $bureau,
                    $voyage
                )
                : sprintf(
                    'Aucun conteneur trouvé pour le manifeste "%s" | Bureau : %s | Voyage : %s',
                    $numero,
                    $bureau,
                    $voyage
                );

            // Informations du manifeste pour le contexte
            $manifesteInfo = [
                'ulid' => $manifeste->ulid ?? null,
                'identifiant' => $manifeste->identifiant ?? null,
                'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                'num_manifeste' => $manifeste->num_manifeste ?? null,
                'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                'code_bureau' => $manifeste->code_bureau ?? null,
                'libelle_bureau' => $manifeste->libelle_bureau ?? null,
                'num_voyage' => $manifeste->num_voyage ?? null,
                'date_manifeste' => $manifeste->date_manifeste ?? null,
                'nom_moyen_transport' => $manifeste->nom_moyen_transport ?? null,
                'nbre_total_conteneur' => $manifeste->nbre_total_conteneur ?? null,
            ];

            return [
                'status' => 200,
                'message' => $message,
                'data' => $items,
                'manifeste' => $manifesteInfo,
                'meta' => [
                    'current_page' => $conteneurs->currentPage(),
                    'last_page' => $conteneurs->lastPage(),
                    'per_page' => $conteneurs->perPage(),
                    'total' => $conteneurs->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/manifestes/sg/{manifeste}/declarations",
     *     summary="Lister les declarations associees a un manifeste",
     *     operationId="manifesteDeclarations",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des declarations",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function declarations(string $manifesteId, Request $request): JsonResponse
    {
        $cacheKey = "manifestes:declarations:{$manifesteId}:" . md5($request->fullUrl());

        $payload = CacheTagger::tags(['manifestes', 'manifeste_sg', 'declarations'])->remember($cacheKey, 300, function () use ($manifesteId, $request) {
            // Récupérer le manifeste par ULID ou ID
            $manifeste = null;
            if (Str::isUlid($manifesteId)) {
                $manifeste = ManifesteSg::where('ulid', $manifesteId)->first();
            } elseif (ctype_digit($manifesteId)) {
                $manifeste = ManifesteSg::where('instance_id', (int) $manifesteId)->first();
            } else {
                $manifeste = ManifesteSg::where('num_manifeste', $manifesteId)->first();
            }

            if (!$manifeste) {
                throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$manifesteId]);
            }

            $perPage = min($request->integer('per_page', 25), 100);
            $declarations = $manifeste->declarations()
                ->orderBy('date_declaration', 'desc')
                ->orderBy('declaration', 'asc')
                ->paginate($perPage);

            $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
            $bureau = $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A';
            $voyage = $manifeste->num_voyage ?? 'N/A';

            // Enrichir chaque déclaration avec les identifiants du manifeste
            $items = collect($declarations->items())->map(function ($declaration) use ($manifeste) {
                $declarationArray = is_array($declaration) ? $declaration : $declaration->toArray();
                $declarationArray['numero_declaration_complet'] = $declaration->numero_declaration_complet ?? null;
                $declarationArray['identifiant'] = $declaration->identifiant ?? null;
                $declarationArray['manifeste'] = [
                    'ulid' => $manifeste->ulid ?? null,
                    'identifiant' => $manifeste->identifiant ?? null,
                    'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                    'num_manifeste' => $manifeste->num_manifeste ?? null,
                    'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                    'code_bureau' => $manifeste->code_bureau ?? null,
                    'nom_bureau' => $manifeste->libelle_bureau ?? null,
                    'num_voyage' => $manifeste->num_voyage ?? null,
                    'date_manifeste' => $manifeste->date_manifeste ?? null,
                ];
                return $declarationArray;
            })->toArray();

            $message = $declarations->total() > 0
                ? sprintf(
                    '%d déclaration(s) trouvée(s) pour le manifeste "%s" | Bureau : %s | Voyage : %s',
                    $declarations->total(),
                    $numero,
                    $bureau,
                    $voyage
                )
                : sprintf(
                    'Aucune déclaration trouvée pour le manifeste "%s" | Bureau : %s | Voyage : %s',
                    $numero,
                    $bureau,
                    $voyage
                );

            // Informations du manifeste pour le contexte
            $manifesteInfo = [
                'ulid' => $manifeste->ulid ?? null,
                'identifiant' => $manifeste->identifiant ?? null,
                'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                'num_manifeste' => $manifeste->num_manifeste ?? null,
                'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                'code_bureau' => $manifeste->code_bureau ?? null,
                'libelle_bureau' => $manifeste->libelle_bureau ?? null,
                'num_voyage' => $manifeste->num_voyage ?? null,
                'date_manifeste' => $manifeste->date_manifeste ?? null,
                'nom_moyen_transport' => $manifeste->nom_moyen_transport ?? null,
            ];

            return [
                'status' => 200,
                'message' => $message,
                'data' => $items,
                'manifeste' => $manifesteInfo,
                'meta' => [
                    'current_page' => $declarations->currentPage(),
                    'last_page' => $declarations->lastPage(),
                    'per_page' => $declarations->perPage(),
                    'total' => $declarations->total(),
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/manifestes/sg/{manifeste}/validate",
     *     summary="Valider la coherence d'un manifeste",
     *     operationId="validateManifeste",
     *     tags={"Manifestes SG"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="manifeste",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultat de la validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="valid", type="boolean")
     *         )
     *     )
     * )
     */
    public function validate(string $manifesteId, Request $request = null): JsonResponse
    {
        // Récupérer le manifeste par ULID ou ID
        $manifeste = null;
        if (Str::isUlid($manifesteId)) {
            $manifeste = ManifesteSg::where('ulid', $manifesteId)->first();
        } elseif (ctype_digit($manifesteId)) {
            $manifeste = ManifesteSg::where('instance_id', (int) $manifesteId)->first();
        } else {
            $manifeste = ManifesteSg::where('num_manifeste', $manifesteId)->first();
        }

        if (!$manifeste) {
            throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$manifesteId]);
        }

        // Champs obligatoires selon la documentation
        $requiredFields = [
            'code_bureau' => 'Code bureau',
            'annee_manifeste' => 'Année du manifeste',
            'num_voyage' => 'Numéro de voyage',
        ];

        // Champs recommandés
        $recommendedFields = [
            'date_voyage' => 'Date de voyage',
            'date_arrivee_navire' => 'Date d\'arrivée du navire',
            'num_manifeste' => 'Numéro de manifeste complet',
            'date_manifeste' => 'Date de manifeste',
            'nom_moyen_transport' => 'Nom du moyen de transport',
            'code_transport' => 'Code mode de transport',
            'code_port_charg' => 'Code port de chargement',
            'code_port_decharg' => 'Code port de déchargement',
            'code_consignataire' => 'Code consignataire',
        ];

        // Vérifier les champs obligatoires
        $missing = collect($requiredFields)
            ->filter(fn ($label, $field) => blank($manifeste->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        // Vérifier les champs recommandés
        $missingRecommended = collect($recommendedFields)
            ->filter(fn ($label, $field) => blank($manifeste->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        $isValid = $missing->isEmpty();
        $isComplete = $missing->isEmpty() && $missingRecommended->isEmpty();

        // Compter les relations
        $titresTransportCount = $manifeste->titresTransport()->count();
        $conteneursCount = $manifeste->conteneurs()->count();
        $declarationsCount = $manifeste->declarations()->count();

        // Vérifier la cohérence des totaux
        $totalBlFromTt = $manifeste->titresTransport()->distinct('num_titre_transport')->count('num_titre_transport');
        $totalColisFromTt = $manifeste->titresTransport()->sum('nbr_colis');
        $totalConteneursFromTc = $manifeste->conteneurs()->count();
        $totalPoidsBrutFromTt = $manifeste->titresTransport()->sum('poids_brut');

        $totauxIssues = [];
        if ($manifeste->nbre_total_bl && $manifeste->nbre_total_bl != $totalBlFromTt) {
            $totauxIssues[] = [
                'field' => 'nbre_total_bl',
                'expected' => $totalBlFromTt,
                'actual' => $manifeste->nbre_total_bl,
                'message' => "Le nombre total de BL ne correspond pas aux titres de transport",
            ];
        }
        if ($manifeste->nbre_total_colis && abs((float)$manifeste->nbre_total_colis - (float)$totalColisFromTt) > 0.01) {
            $totauxIssues[] = [
                'field' => 'nbre_total_colis',
                'expected' => $totalColisFromTt,
                'actual' => $manifeste->nbre_total_colis,
                'message' => "Le nombre total de colis ne correspond pas aux titres de transport",
            ];
        }
        if ($manifeste->nbre_total_conteneur && $manifeste->nbre_total_conteneur != $totalConteneursFromTc) {
            $totauxIssues[] = [
                'field' => 'nbre_total_conteneur',
                'expected' => $totalConteneursFromTc,
                'actual' => $manifeste->nbre_total_conteneur,
                'message' => "Le nombre total de conteneurs ne correspond pas aux conteneurs enregistrés",
            ];
        }

        $numero = $manifeste->numero_manifeste_complet ?? $manifeste->identifiant ?? "N°{$manifeste->instance_id}";
        $bureau = $manifeste->libelle_bureau ?? $manifeste->code_bureau ?? 'N/A';
        $voyage = $manifeste->num_voyage ?? 'N/A';

        $completionRate = count($requiredFields) > 0 
            ? round((count($requiredFields) - $missing->count()) / count($requiredFields) * 100, 1)
            : 100;

        // Déterminer le message de validation
        $validationIssues = [];
        if (!$missing->isEmpty()) {
            $validationIssues[] = "Champs obligatoires manquants : " . $missing->pluck('label')->join(', ');
        }
        if (!empty($totauxIssues)) {
            $validationIssues[] = "Incohérences dans les totaux";
        }
        if ($titresTransportCount === 0) {
            $validationIssues[] = "Aucun titre de transport associé";
        }

        $isFullyValid = $isValid && empty($totauxIssues) && $titresTransportCount > 0;

        $message = $isFullyValid
            ? ($isComplete 
                ? "Le manifeste \"{$numero}\" est valide et complet ({$completionRate}% complète) | Bureau : {$bureau} | Voyage : {$voyage} | {$titresTransportCount} BL | {$conteneursCount} conteneur(s) | {$declarationsCount} déclaration(s)"
                : "Le manifeste \"{$numero}\" est valide mais des informations recommandées sont manquantes ({$completionRate}% complète) | Bureau : {$bureau} | Voyage : {$voyage} | {$titresTransportCount} BL | {$conteneursCount} conteneur(s) | {$declarationsCount} déclaration(s)")
            : "Le manifeste \"{$numero}\" est invalide : " . implode(' | ', $validationIssues) . " ({$completionRate}% complète) | Bureau : {$bureau} | Voyage : {$voyage}";

        // Log the validation
        AuditService::log(
            'validate', 
            "Validation du manifeste \"{$numero}\" - " . ($isFullyValid ? ($isComplete ? 'Valide et complet' : 'Valide mais incomplet') : 'Invalide'), 
            'ManifesteSg', 
            $manifeste->instance_id, 
            null, 
            [
                'valid' => $isFullyValid,
                'complete' => $isComplete,
                'completion_rate' => $completionRate,
                'missing_fields' => $missing->toArray(),
                'missing_recommended' => $missingRecommended->toArray(),
                'totaux_issues' => $totauxIssues,
                'titres_transport_count' => $titresTransportCount,
                'conteneurs_count' => $conteneursCount,
                'declarations_count' => $declarationsCount,
            ]
        );

        // Invalider le cache
        CacheTagger::tags(['manifestes', 'manifeste_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => [
                'valid' => $isFullyValid,
                'complete' => $isComplete,
                'completion_rate' => $completionRate,
                'missing_fields' => $missing->toArray(),
                'missing_recommended_fields' => $missingRecommended->toArray(),
                'totaux_issues' => $totauxIssues,
                'manifeste' => [
                    'ulid' => $manifeste->ulid ?? null,
                    'identifiant' => $manifeste->identifiant ?? null,
                    'numero_manifeste_complet' => $manifeste->numero_manifeste_complet ?? null,
                    'num_manifeste' => $manifeste->num_manifeste ?? null,
                    'annee_manifeste' => $manifeste->annee_manifeste ?? null,
                    'code_bureau' => $manifeste->code_bureau ?? null,
                    'libelle_bureau' => $manifeste->libelle_bureau ?? null,
                    'num_voyage' => $manifeste->num_voyage ?? null,
                ],
                'relations' => [
                    'titres_transport' => [
                        'count' => $titresTransportCount,
                        'has_titres_transport' => $titresTransportCount > 0,
                        'url' => "/api/manifestes/sg/{$manifesteId}/titres-transport",
                    ],
                    'conteneurs' => [
                        'count' => $conteneursCount,
                        'has_conteneurs' => $conteneursCount > 0,
                        'url' => "/api/manifestes/sg/{$manifesteId}/conteneurs",
                    ],
                    'declarations' => [
                        'count' => $declarationsCount,
                        'has_declarations' => $declarationsCount > 0,
                        'url' => "/api/manifestes/sg/{$manifesteId}/declarations",
                    ],
                ],
                'totaux' => [
                    'nbre_total_bl' => [
                        'expected' => $totalBlFromTt,
                        'actual' => $manifeste->nbre_total_bl,
                        'consistent' => !$manifeste->nbre_total_bl || $manifeste->nbre_total_bl == $totalBlFromTt,
                    ],
                    'nbre_total_colis' => [
                        'expected' => $totalColisFromTt,
                        'actual' => $manifeste->nbre_total_colis,
                        'consistent' => !$manifeste->nbre_total_colis || abs((float)$manifeste->nbre_total_colis - (float)$totalColisFromTt) < 0.01,
                    ],
                    'nbre_total_conteneur' => [
                        'expected' => $totalConteneursFromTc,
                        'actual' => $manifeste->nbre_total_conteneur,
                        'consistent' => !$manifeste->nbre_total_conteneur || $manifeste->nbre_total_conteneur == $totalConteneursFromTc,
                    ],
                    'total_poids_brut' => [
                        'expected' => $totalPoidsBrutFromTt,
                        'actual' => $manifeste->total_poids_brut,
                        'consistent' => !$manifeste->total_poids_brut || abs((float)$manifeste->total_poids_brut - (float)$totalPoidsBrutFromTt) < 0.01,
                    ],
                ],
            ],
        ]);
    }

    private function findManifeste(string $identifier): ManifesteSg
    {
        $manifeste = null;

        if (Str::isUlid($identifier)) {
            $manifeste = ManifesteSg::where('ulid', $identifier)->first();
        } elseif (ctype_digit($identifier)) {
            $manifeste = ManifesteSg::where('instance_id', (int) $identifier)->first();
        } else {
            $manifeste = ManifesteSg::where('num_manifeste', $identifier)->first();
        }

        if (!$manifeste) {
            throw (new ModelNotFoundException())->setModel(ManifesteSg::class, [$identifier]);
        }

        return $manifeste;
    }
}


