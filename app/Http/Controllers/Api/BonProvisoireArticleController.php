<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BonProvisoireArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonProvisoireArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 25), 100);
        $numero = $request->string('numero_bon_provisoire')->toString();

        $paginator = BonProvisoireArticle::query()
            ->when($numero, fn ($query) => $query->where('numero_bon_provisoire', $numero))
            ->latest('date_bp')
            ->paginate($perPage);

        return response()->json($this->formatPaginator($paginator));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $article = BonProvisoireArticle::create($validated);

        return response()->json(['data' => $article->fresh()->toArray()], 201);
    }

    public function show(BonProvisoireArticle $bonProvisoireArticle): JsonResponse
    {
        return response()->json(['data' => $bonProvisoireArticle->toArray()]);
    }

    public function update(Request $request, BonProvisoireArticle $bonProvisoireArticle): JsonResponse
    {
        $validated = $request->validate($this->rules(true));

        $bonProvisoireArticle->fill($validated);
        $bonProvisoireArticle->save();

        return response()->json(['data' => $bonProvisoireArticle->fresh()->toArray()]);
    }

    public function destroy(BonProvisoireArticle $bonProvisoireArticle): JsonResponse
    {
        $bonProvisoireArticle->delete();

        return response()->json(['status' => 'deleted']);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new BonProvisoireArticle())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['instance_id'] = $update ? 'sometimes|integer' : 'required|integer';
        $rules['numero_bon_provisoire'] = $update ? 'sometimes|string|max:120' : 'required|string|max:120';

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





