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

        $cacheKey = sprintf('fcvr_article.index.%s.%s', $request->get('page', 1), md5($search.$perPage));

        $data = CacheTagger::tags(['fcvr_article'])->remember($cacheKey, 300, function () use ($search, $perPage) {
            $query = FcvrArticle::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_article', 'like', "%{$search}%")
                      ->orWhere('designation', 'like', "%{$search}%");
                });
            }

            $articles = $query->latest()->paginate($perPage);

            return [
                'status' => 200,
                'data' => $articles->items(),
                'meta' => [
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                    'per_page' => $articles->perPage(),
                    'total' => $articles->total(),
                ],
            ];
        });

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $article = FcvrArticle::create($request->all());
        $article->refresh();

        CacheTagger::tags(['fcvr_article'])->flush();

        return response()->json([
            'status' => 201,
            'message' => 'Article FCVR créé avec succès',
            'data' => $article->toArray(),
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


