<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fdi\StoreFdiRechCompRequest;
use App\Http\Requests\Fdi\UpdateFdiRechCompRequest;
use App\Models\FdiRechComp;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class FdiRechCompController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $perPage = min($request->integer('per_page', 25), 100);
        $search = $request->string('search')->toString();
        $fdiPrimaire = $request->string('fdi_primaire')->toString();
        $fdiSecondaire = $request->string('fdi_secondaire')->toString();
        $dateDebut = $request->string('date_debut')->toString();
        $dateFin = $request->string('date_fin')->toString();

        // Construire la clé de cache avec tous les paramètres de recherche
        $cacheKey = sprintf(
            'fdi_rech_comp.index.%s.%s.%s',
            $page,
            $perPage,
            md5($search . $fdiPrimaire . $fdiSecondaire . $dateDebut . $dateFin)
        );

        $payload = CacheTagger::tags(['fdi_rech_comp'])->remember($cacheKey, now()->addMinutes(5), function () use ($search, $fdiPrimaire, $fdiSecondaire, $dateDebut, $dateFin, $perPage) {
            $query = FdiRechComp::query()->with(['fdiPrimaire', 'fdiSecondaire']);

            // Recherche Google-like sur plusieurs champs (selon s360_analyse)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('fdi_primaire', 'ILIKE', "%{$search}%")
                        ->orWhere('fdi_secondaire', 'ILIKE', "%{$search}%")
                        ->orWhere('observation', 'ILIKE', "%{$search}%")
                        ->orWhere('id_fdi_comp', 'ILIKE', "%{$search}%");
                });
            }

            // Filtres spécifiques
            if ($fdiPrimaire) {
                $query->where('fdi_primaire', $fdiPrimaire);
            }

            if ($fdiSecondaire) {
                $query->where('fdi_secondaire', $fdiSecondaire);
            }

            if ($dateDebut) {
                $query->where('date_fdi_primaire', '>=', $dateDebut);
            }

            if ($dateFin) {
                $query->where('date_fdi_primaire', '<=', $dateFin);
            }

            // Tri par date de création décroissante
            $query->latest('created_at');

            $paginator = $query->paginate($perPage);

            // Enrichir chaque comparaison avec identifiants et message résumé
            $enrichedItems = collect($paginator->items())->map(function ($comparaison) {
                $fdiPrimaire = $comparaison->fdiPrimaire;
                $fdiSecondaire = $comparaison->fdiSecondaire;

                // Construire l'identifiant de la comparaison
                $identifiant = $comparaison->fdi_primaire && $comparaison->fdi_secondaire
                    ? "{$comparaison->fdi_primaire} vs {$comparaison->fdi_secondaire}"
                    : ($comparaison->ulid ?? "COMP-{$comparaison->id}");

                // Construire le message résumé
                $messageParts = [];
                if ($fdiPrimaire) {
                    $numeroPrimaire = $fdiPrimaire->numero_fdi_complet ?? $fdiPrimaire->numero_fdi ?? $comparaison->fdi_primaire;
                    $messageParts[] = "FDI Primaire: {$numeroPrimaire}";
                } else {
                    $messageParts[] = "FDI Primaire: {$comparaison->fdi_primaire}";
                }

                if ($fdiSecondaire) {
                    $numeroSecondaire = $fdiSecondaire->numero_fdi_complet ?? $fdiSecondaire->numero_fdi ?? $comparaison->fdi_secondaire;
                    $messageParts[] = "FDI Secondaire: {$numeroSecondaire}";
                } else {
                    $messageParts[] = "FDI Secondaire: {$comparaison->fdi_secondaire}";
                }

                if ($comparaison->observation) {
                    $messageParts[] = "Observation: " . substr($comparaison->observation, 0, 50) . (strlen($comparaison->observation) > 50 ? '...' : '');
                }

                $messageResume = implode(' | ', $messageParts);

                // Ajouter les champs enrichis
                $comparaisonArray = $comparaison->toArray();
                $comparaisonArray['identifiant'] = $identifiant;
                $comparaisonArray['message_resume'] = $messageResume;

                // Ajouter les informations des FDI liées
                if ($fdiPrimaire) {
                    $comparaisonArray['fdi_primaire_info'] = [
                        'ulid' => $fdiPrimaire->ulid,
                        'identifiant' => $fdiPrimaire->identifiant,
                        'numero_fdi_complet' => $fdiPrimaire->numero_fdi_complet,
                        'numero_fdi' => $fdiPrimaire->numero_fdi,
                        'importateur' => $fdiPrimaire->importateur,
                        'valeur_caf' => $fdiPrimaire->valeur_caf,
                    ];
                }

                if ($fdiSecondaire) {
                    $comparaisonArray['fdi_secondaire_info'] = [
                        'ulid' => $fdiSecondaire->ulid,
                        'identifiant' => $fdiSecondaire->identifiant,
                        'numero_fdi_complet' => $fdiSecondaire->numero_fdi_complet,
                        'numero_fdi' => $fdiSecondaire->numero_fdi,
                        'importateur' => $fdiSecondaire->importateur,
                        'valeur_caf' => $fdiSecondaire->valeur_caf,
                    ];
                }

                return $comparaisonArray;
            });

            // Construire le message de réponse
            $total = $paginator->total();
            $message = '';
            
            if ($total > 0) {
                if ($search || $fdiPrimaire || $fdiSecondaire || $dateDebut || $dateFin) {
                    $filters = [];
                    if ($search) $filters[] = "recherche: \"{$search}\"";
                    if ($fdiPrimaire) $filters[] = "FDI Primaire: \"{$fdiPrimaire}\"";
                    if ($fdiSecondaire) $filters[] = "FDI Secondaire: \"{$fdiSecondaire}\"";
                    if ($dateDebut) $filters[] = "Date début: {$dateDebut}";
                    if ($dateFin) $filters[] = "Date fin: {$dateFin}";
                    
                    $message = "{$total} comparaison(s) trouvée(s) avec " . implode(', ', $filters);
                } else {
                    $message = "{$total} comparaison(s) FDI récupérée(s) avec succès";
                }
            } else {
                if ($search || $fdiPrimaire || $fdiSecondaire || $dateDebut || $dateFin) {
                    $filters = [];
                    if ($search) $filters[] = "recherche: \"{$search}\"";
                    if ($fdiPrimaire) $filters[] = "FDI Primaire: \"{$fdiPrimaire}\"";
                    if ($fdiSecondaire) $filters[] = "FDI Secondaire: \"{$fdiSecondaire}\"";
                    if ($dateDebut) $filters[] = "Date début: {$dateDebut}";
                    if ($dateFin) $filters[] = "Date fin: {$dateFin}";
                    $message = "Aucune comparaison trouvée pour les filtres: " . implode(', ', $filters);
                } else {
                    $message = "Aucune comparaison FDI enregistrée pour le moment";
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

    public function store(StoreFdiRechCompRequest $request): JsonResponse
    {
        $rechComp = FdiRechComp::create($request->validated());
        $rechComp->refresh();
        $rechComp->load(['fdiPrimaire', 'fdiSecondaire']);

        // Enrichir avec les informations des FDI
        $fdiPrimaire = $rechComp->fdiPrimaire;
        $fdiSecondaire = $rechComp->fdiSecondaire;

        // Construire l'identifiant de la comparaison
        $identifiant = $rechComp->fdi_primaire && $rechComp->fdi_secondaire
            ? "{$rechComp->fdi_primaire} vs {$rechComp->fdi_secondaire}"
            : ($rechComp->ulid ?? "COMP-{$rechComp->id}");

        // Construire le message résumé
        $messageParts = [];
        if ($fdiPrimaire) {
            $numeroPrimaire = $fdiPrimaire->numero_fdi_complet ?? $fdiPrimaire->numero_fdi ?? $rechComp->fdi_primaire;
            $messageParts[] = "FDI Primaire: {$numeroPrimaire}";
        } else {
            $messageParts[] = "FDI Primaire: {$rechComp->fdi_primaire}";
        }

        if ($fdiSecondaire) {
            $numeroSecondaire = $fdiSecondaire->numero_fdi_complet ?? $fdiSecondaire->numero_fdi ?? $rechComp->fdi_secondaire;
            $messageParts[] = "FDI Secondaire: {$numeroSecondaire}";
        } else {
            $messageParts[] = "FDI Secondaire: {$rechComp->fdi_secondaire}";
        }

        if ($rechComp->observation) {
            $messageParts[] = "Observation: " . substr($rechComp->observation, 0, 50) . (strlen($rechComp->observation) > 50 ? '...' : '');
        }

        $messageResume = implode(' | ', $messageParts);

        // Construire le message de réponse enrichi
        $details = [];
        if ($fdiPrimaire) {
            $numeroPrimaire = $fdiPrimaire->numero_fdi_complet ?? $fdiPrimaire->numero_fdi ?? $rechComp->fdi_primaire;
            $details[] = "FDI Primaire: {$numeroPrimaire}";
        }
        if ($fdiSecondaire) {
            $numeroSecondaire = $fdiSecondaire->numero_fdi_complet ?? $fdiSecondaire->numero_fdi ?? $rechComp->fdi_secondaire;
            $details[] = "FDI Secondaire: {$numeroSecondaire}";
        }
        if ($rechComp->observation) {
            $details[] = "Observation: " . substr($rechComp->observation, 0, 30) . (strlen($rechComp->observation) > 30 ? '...' : '');
        }
        $detailsStr = !empty($details) ? " | " . implode(' | ', $details) : '';

        $message = "Comparaison FDI créée avec succès{$detailsStr}";

        // Construire les données enrichies
        $data = $rechComp->toArray();
        $data['identifiant'] = $identifiant;
        $data['message_resume'] = $messageResume;

        // Ajouter les informations des FDI liées
        $relations = [];
        if ($fdiPrimaire) {
            $relations['fdi_primaire'] = [
                'ulid' => $fdiPrimaire->ulid,
                'identifiant' => $fdiPrimaire->identifiant,
                'numero_fdi_complet' => $fdiPrimaire->numero_fdi_complet,
                'numero_fdi' => $fdiPrimaire->numero_fdi,
                'annee' => $fdiPrimaire->annee,
                'bureau' => $fdiPrimaire->bureau,
                'importateur' => $fdiPrimaire->importateur,
                'valeur_caf' => $fdiPrimaire->valeur_caf,
                'date_fdi' => $fdiPrimaire->date_fdi?->toIso8601String(),
            ];
        }

        if ($fdiSecondaire) {
            $relations['fdi_secondaire'] = [
                'ulid' => $fdiSecondaire->ulid,
                'identifiant' => $fdiSecondaire->identifiant,
                'numero_fdi_complet' => $fdiSecondaire->numero_fdi_complet,
                'numero_fdi' => $fdiSecondaire->numero_fdi,
                'annee' => $fdiSecondaire->annee,
                'bureau' => $fdiSecondaire->bureau,
                'importateur' => $fdiSecondaire->importateur,
                'valeur_caf' => $fdiSecondaire->valeur_caf,
                'date_fdi' => $fdiSecondaire->date_fdi?->toIso8601String(),
            ];
        }

        AuditService::log('create', "Nouvelle comparaison FDI créée: {$identifiant}", 'FdiRechComp', $rechComp->id, null, $rechComp->toArray());

        CacheTagger::tags(['fdi_rech_comp'])->flush();

        return response()->json([
            'status' => 201,
            'message' => $message,
            'data' => $data,
            'relations' => $relations,
        ], 201);
    }

    public function show(string $fdiRechComp): JsonResponse
    {
        // Récupérer la comparaison par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($fdiRechComp)) {
            $comparaison = FdiRechComp::where('ulid', $fdiRechComp)->firstOrFail();
        } elseif (is_numeric($fdiRechComp)) {
            $comparaison = FdiRechComp::where('id', $fdiRechComp)->firstOrFail();
        } else {
            $comparaison = FdiRechComp::where('ulid', $fdiRechComp)->firstOrFail();
        }
        
        $cacheKey = "fdi_rech_comp.show." . ($comparaison->ulid ?? $comparaison->id);
        
        $data = CacheTagger::tags(['fdi_rech_comp'])->remember(
            $cacheKey,
            now()->addMinutes(5),
            fn () => $comparaison->load(['fdiPrimaire', 'fdiSecondaire'])->toArray()
        );

        return response()->json([
            'status' => 200,
            'message' => 'Comparaison FDI récupérée avec succès',
            'data' => $data
        ]);
    }

    public function update(UpdateFdiRechCompRequest $request, string $fdiRechComp): JsonResponse
    {
        // Récupérer la comparaison par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($fdiRechComp)) {
            $comparaison = FdiRechComp::where('ulid', $fdiRechComp)->firstOrFail();
        } elseif (is_numeric($fdiRechComp)) {
            $comparaison = FdiRechComp::where('id', $fdiRechComp)->firstOrFail();
        } else {
            $comparaison = FdiRechComp::where('ulid', $fdiRechComp)->firstOrFail();
        }
        
        $comparaison->update($request->validated());
        CacheTagger::tags(['fdi_rech_comp'])->flush();

        return response()->json([
            'status' => 200,
            'message' => 'Comparaison FDI modifiée avec succès',
            'data' => $comparaison->fresh()
        ]);
    }

    public function destroy(string $fdiRechComp): JsonResponse
    {
        // Récupérer la comparaison par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($fdiRechComp)) {
            $comparaison = FdiRechComp::where('ulid', $fdiRechComp)->firstOrFail();
        } elseif (is_numeric($fdiRechComp)) {
            $comparaison = FdiRechComp::where('id', $fdiRechComp)->firstOrFail();
        } else {
            $comparaison = FdiRechComp::where('ulid', $fdiRechComp)->firstOrFail();
        }
        
        $comparaison->delete();
        CacheTagger::tags(['fdi_rech_comp'])->flush();

        return response()->json([
            'status' => 200,
            'message' => 'Comparaison FDI supprimée avec succès',
            'data' => null
        ]);
    }

}

