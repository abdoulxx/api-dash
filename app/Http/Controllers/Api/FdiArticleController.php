<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fdi\StoreFdiArticleRequest;
use App\Http\Requests\Fdi\UpdateFdiArticleRequest;
use App\Models\FdiArticle;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use App\Support\CacheTagger;

class FdiArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $perPage = min($request->integer('per_page', 25), 100);
        $fdi = $request->string('numero_fdi')->toString();

        $cacheKey = sprintf('fdi_article.index.%s.%s.%s', $page, $perPage, md5($fdi));

        $payload = CacheTagger::tags(['fdi_articles'])->remember($cacheKey, now()->addMinutes(5), function () use ($fdi, $perPage) {
            $paginator = FdiArticle::query()
                ->when($fdi, fn ($query) => $query->where('numero_fdi', $fdi))
                ->orderBy('numero_fdi')
                ->paginate($perPage);

            $message = $paginator->total() > 0
                ? ($fdi
                    ? "{$paginator->total()} article(s) trouvé(s) pour la FDI \"{$fdi}\""
                    : "{$paginator->total()} article(s) FDI récupéré(s) avec succès")
                : ($fdi
                    ? "Aucun article trouvé pour la FDI \"{$fdi}\""
                    : "Aucun article FDI enregistré pour le moment");

            return [
                'status' => 200,
                'message' => $message,
                'data' => $paginator->items(),
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

    public function store(StoreFdiArticleRequest $request): JsonResponse
    {
        $article = FdiArticle::create($request->validated());
        $article->refresh();

        // Log the creation
        $numero = $article->numero_fdi ?? "N°{$article->id}";
        $postar = $article->postar ? " (code tarifaire: {$article->postar})" : "";
        $quantite = $article->quantite ? " - Quantité: {$article->quantite}" : "";
        
        AuditService::log('create', "Nouvel article ajouté à la FDI \"{$numero}\" - Article n°{$article->numart}{$postar}", 'FdiArticle', $article->id, null, $article->toArray());

        CacheTagger::tags(['fdi_articles', 'fdi_sg'])->flush();

        $message = "Article n°{$article->numart} ajouté avec succès à la FDI \"{$numero}\"{$postar}{$quantite}";

        return response()->json([
            'status' => 201,
            'message' => $message,
            'data' => $article->toArray()
        ], 201);
    }

    public function show(string $article): JsonResponse
    {
        // Récupérer l'article par ULID ou ID depuis la route
        // Le route model binding peut ne pas fonctionner correctement, donc on le fait manuellement
        // Si c'est un ULID, chercher uniquement par ulid (pour éviter l'erreur SQL avec id bigint)
        if (\Illuminate\Support\Str::isUlid($article)) {
            $fdiArticle = FdiArticle::where('ulid', $article)->first();
        } 
        // Si c'est numérique, chercher par id
        elseif (is_numeric($article)) {
            $fdiArticle = FdiArticle::where('id', $article)->first();
        } 
        // Sinon, chercher par ulid uniquement (le plus sûr)
        else {
            $fdiArticle = FdiArticle::where('ulid', $article)->first();
        }
        
        if (!$fdiArticle) {
            return response()->json([
                'status' => 404,
                'message' => "L'article FDI demandé n'existe pas ou a été supprimé",
                'data' => null
            ], 404);
        }
        
        // Construire la clé de cache avec ULID si disponible, sinon ID
        $cacheKey = "fdi_article.show." . ($fdiArticle->ulid ?? $fdiArticle->id);
        
        // Récupérer les données depuis le cache ou la base de données
        $payload = CacheTagger::tags(['fdi_articles'])->remember(
            $cacheKey,
            now()->addMinutes(5),
            function () use ($fdiArticle) {
                // Récupérer directement depuis la base pour éviter les problèmes de cache
                $article = FdiArticle::find($fdiArticle->id);
                
                if (!$article) {
                    return null;
                }
                
                $numero = $article->numero_fdi ?? "N°{$article->id}";
                $postar = $article->postar ? " (code: {$article->postar})" : "";
                
                // Retourner tous les attributs explicitement
                return [
                    'status' => 200,
                    'message' => "Détails de l'article n°{$article->numart} de la FDI \"{$numero}\"{$postar} récupérés",
                    'data' => [
                        'id' => $article->id,
                        'ulid' => $article->ulid,
                        'instance_id' => $article->instance_id,
                        'serie_fdi' => $article->serie_fdi,
                        'bureau' => $article->bureau,
                        'annee' => $article->annee,
                        'numero_serie' => $article->numero_serie,
                        'numero_fdi' => $article->numero_fdi,
                        'date_fdi' => $article->date_fdi?->toIso8601String(),
                        'numart' => $article->numart,
                        'postar' => $article->postar,
                        'nature_marchandise' => $article->nature_marchandise,
                        'description_marchandise' => $article->description_marchandise,
                        'quantite' => $article->quantite ? (string) $article->quantite : null,
                        'poids_net' => $article->poids_net ? (string) $article->poids_net : null,
                        'poids_brut' => $article->poids_brut ? (string) $article->poids_brut : null,
                        'created_at' => $article->created_at?->toIso8601String(),
                        'updated_at' => $article->updated_at?->toIso8601String(),
                        'deleted_at' => $article->deleted_at?->toIso8601String(),
                    ],
                ];
            }
        );

        if ($payload === null || !isset($payload['data'])) {
            return response()->json([
                'status' => 404,
                'message' => "L'article FDI demandé n'existe pas ou a été supprimé",
                'data' => null
            ], 404);
        }

        return response()->json($payload);
    }

    public function update(UpdateFdiArticleRequest $request, string $article): JsonResponse
    {
        // Récupérer l'article par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($article)) {
            $fdiArticle = FdiArticle::where('ulid', $article)->firstOrFail();
        } elseif (is_numeric($article)) {
            $fdiArticle = FdiArticle::where('id', $article)->firstOrFail();
        } else {
            $fdiArticle = FdiArticle::where('ulid', $article)->firstOrFail();
        }
        
        $oldValues = $fdiArticle->toArray();
        $fdiArticle->update($request->validated());
        $fdiArticle->refresh();

        // Log the update
        $numero = $fdiArticle->numero_fdi ?? "N°{$fdiArticle->id}";
        $postar = $fdiArticle->postar ? " (code: {$fdiArticle->postar})" : "";
        $champsModifies = array_keys(array_diff_assoc($fdiArticle->toArray(), $oldValues));
        $nbChamps = count($champsModifies);
        
        AuditService::log('update', "Article n°{$fdiArticle->numart} de la FDI \"{$numero}\" mis à jour ({$nbChamps} champ(s) modifié(s))", 'FdiArticle', $fdiArticle->id, $oldValues, $fdiArticle->toArray());

        CacheTagger::tags(['fdi_articles', 'fdi_sg'])->flush();

        $message = $nbChamps > 0
            ? "Article n°{$fdiArticle->numart} de la FDI \"{$numero}\"{$postar} mis à jour avec succès ({$nbChamps} modification(s))"
            : "Article n°{$fdiArticle->numart} de la FDI \"{$numero}\"{$postar} - aucune modification détectée";

        return response()->json([
            'status' => 200,
            'message' => $message,
            'data' => $fdiArticle->toArray()
        ]);
    }

    public function destroy(string $article): JsonResponse
    {
        // Récupérer l'article par ULID ou ID
        if (\Illuminate\Support\Str::isUlid($article)) {
            $fdiArticle = FdiArticle::where('ulid', $article)->firstOrFail();
        } elseif (is_numeric($article)) {
            $fdiArticle = FdiArticle::where('id', $article)->firstOrFail();
        } else {
            $fdiArticle = FdiArticle::where('ulid', $article)->firstOrFail();
        }
        
        $numero = $fdiArticle->numero_fdi ?? "N°{$fdiArticle->id}";
        $postar = $fdiArticle->postar ? " (code: {$fdiArticle->postar})" : "";
        $oldValues = $fdiArticle->toArray();
        
        $fdiArticle->delete();

        // Log the deletion
        AuditService::log('delete', "Article n°{$fdiArticle->numart} retiré de la FDI \"{$numero}\" (suppression temporaire)", 'FdiArticle', $fdiArticle->id, $oldValues, null);

        CacheTagger::tags(['fdi_articles', 'fdi_sg'])->flush();

        return response()->json([
            'status' => 200,
            'message' => "Article n°{$fdiArticle->numart} retiré de la FDI \"{$numero}\"{$postar} - suppression temporaire (peut être restauré)",
            'data' => null
        ]);
    }

    private function formatPaginator(LengthAwarePaginator $paginator): array
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
}

