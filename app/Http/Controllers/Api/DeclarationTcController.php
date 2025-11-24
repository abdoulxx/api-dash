<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeclarationTc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeclarationTcController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 25), 100);
        $manifeste = $request->string('num_manifeste')->toString();

        $paginator = DeclarationTc::query()
            ->when($manifeste, fn ($query) => $query->where('num_manifeste', $manifeste))
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json($this->formatPaginator($paginator));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $conteneur = DeclarationTc::create($validated);

        return response()->json(['data' => $conteneur->fresh()->toArray()], 201);
    }

    public function show(DeclarationTc $declarationTc): JsonResponse
    {
        return response()->json(['data' => $declarationTc->toArray()]);
    }

    public function update(Request $request, DeclarationTc $declarationTc): JsonResponse
    {
        $validated = $request->validate($this->rules(true));

        $declarationTc->fill($validated);
        $declarationTc->save();

        return response()->json(['data' => $declarationTc->fresh()->toArray()]);
    }

    public function destroy(DeclarationTc $declarationTc): JsonResponse
    {
        $declarationTc->delete();

        return response()->json(['status' => 'deleted']);
    }

    private function rules(bool $update = false): array
    {
        $rules = [];

        foreach ((new DeclarationTc())->getFillable() as $attribute) {
            $rules[$attribute] = $update ? 'sometimes' : 'nullable';
        }

        $rules['instanceid'] = $update ? 'sometimes|integer' : 'required|integer';
        $rules['num_manifeste'] = $update ? 'sometimes|string|max:120' : 'required|string|max:120';
        $rules['numero_conteneur'] = $update ? 'sometimes|string|max:80' : 'required|string|max:80';

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




