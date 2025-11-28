<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcvrArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\CacheTagger;

class FcvrArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $search = $request->string('search')->toString();
        $page = $request->integer('page', 1);

        $cacheKey = sprintf('fcvr_article.index.%s.%s', $page, md5($search.$perPage));

        $payload = CacheTagger::tags(['fcvr_article'])->remember($cacheKey, 300, function () use ($search, $perPage, $page) {
            $query = FcvrArticle::query()->with('fcvr');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_article', 'like', "%{$search}%")
                        ->orWhere('sh_rfcv', 'like', "%{$search}%")
                        ->orWhere('libelle_sh_rfcv', 'like', "%{$search}%")
                        ->orWhere('num_rfcv', 'like', "%{$search}%");
                });
            }

            $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()
                ->map(function (FcvrArticle $article) {
                    $data = $article->toArray();
                    $fcvr = $article->fcvr;
                    $data['fcvr_identifiant'] = $fcvr?->identifiant;
                    $data['numero_fcvr_complet'] = $fcvr?->numero_fcvr_complet;
                    $data['message_resume'] = sprintf(
                        '%s | Article #%s | SH %s | Quantité : %s %s | CAF article : %s',
                        $fcvr?->identifiant ?? $article->num_rfcv ?? 'FCVR inconnue',
                        $article->num_article ?? 'N/A',
                        $article->sh_rfcv ?? 'N/A',
                        $article->quantite_article ?? '0',
                        $article->unite_quantite ?? '',
                        $article->caf_article ?? $article->caf_declaree ?? '0'
                    );
                    return $data;
                })
                ->values()
                ->toArray();

            $lastPage = max($paginator->lastPage(), 1);
            $message = $paginator->total() > 0
                ? ($search
                    ? sprintf('Recherche "%s" : %d article(s) FCVR. Page %d/%d', $search, $paginator->total(), $paginator->currentPage(), $lastPage)
                    : sprintf('Liste des articles FCVR : %d enregistrement(s). Page %d/%d', $paginator->total(), $paginator->currentPage(), $lastPage))
                : ($search
                    ? sprintf('Aucun article ne correspond à "%s". Essayez avec un numéro FCVR, article ou SH', $search)
                    : 'Aucun article FCVR enregistré.');

            return [
                'status' => 200,
                'message' => $message,
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        $article = FcvrArticle::create($payload);
        $article->refresh();

        CacheTagger::tags(['fcvr_article'])->flush();

        $data = $article->toArray();
        $fcvr = $article->fcvr;
        $data['fcvr_identifiant'] = $fcvr?->identifiant;
        $data['numero_fcvr_complet'] = $fcvr?->numero_fcvr_complet;

        return response()->json([
            'status' => 201,
            'message' => sprintf(
                "Article #%s ajouté à la FCVR \"%s\"",
                $article->num_article ?? $article->id,
                $fcvr?->identifiant ?? $article->num_rfcv ?? 'FCVR inconnue'
            ),
            'data' => $data,
        ], 201);
    }

    public function show(FcvrArticle $fcvrArticle): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'data' => $fcvrArticle->toArray(),
        ]);
    }

    public function update(Request $request, FcvrArticle $fcvrArticle): JsonResponse
    {
        $fcvrArticle->update($request->all());
        $fcvrArticle->refresh();

        CacheTagger::tags(['fcvr_article'])->flush();

        return response()->json([
            'status' => 200,
            'message' => 'Article FCVR mis à jour avec succès',
            'data' => $fcvrArticle->toArray(),
        ]);
    }

    public function destroy(FcvrArticle $fcvrArticle): JsonResponse
    {
        $fcvrArticle->delete();

        CacheTagger::tags(['fcvr_article'])->flush();

        return response()->json([
            'status' => 200,
            'message' => 'Article FCVR supprimé avec succès',
        ]);
    }
}










