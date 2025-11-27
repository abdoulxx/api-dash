<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeclarationArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeclarationArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 25), 100);
        $declaration = $request->string('declaration')->toString();

        $paginator = DeclarationArticle::query()
            ->when($declaration, fn ($query) => $query->where('declaration', $declaration))
            ->latest('date_declaration')
            ->paginate($perPage);

        return response()->json($this->formatPaginator($paginator));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $article = DeclarationArticle::create($validated);

        return response()->json(['data' => $article->fresh()->toArray()], 201);
    }

    public function show(DeclarationArticle $declarationArticle): JsonResponse
    {
        return response()->json(['data' => $declarationArticle->toArray()]);
    }

    public function update(Request $request, DeclarationArticle $declarationArticle): JsonResponse
    {
        $validated = $request->validate($this->rules(true));

        $declarationArticle->fill($validated);
        $declarationArticle->save();

        return response()->json(['data' => $declarationArticle->fresh()->toArray()]);
    }

    public function destroy(DeclarationArticle $declarationArticle): JsonResponse
    {
        $declarationArticle->delete();

        return response()->json(['status' => 'deleted']);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new DeclarationArticle())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['instanceid'] = $update ? 'sometimes|integer' : 'required|integer';
        $rules['declaration'] = $update ? 'sometimes|string|max:240' : 'required|string|max:240';
        $rules['numero_article'] = $update ? 'sometimes|integer' : 'required|integer';

        return $rules;
    }

    private function formatPaginator($paginator): array
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





