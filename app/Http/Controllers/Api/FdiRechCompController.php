<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fdi\StoreFdiRechCompRequest;
use App\Http\Requests\Fdi\UpdateFdiRechCompRequest;
use App\Models\FdiRechComp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use App\Support\CacheTagger;

class FdiRechCompController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $perPage = min($request->integer('per_page', 25), 100);
        $fdi = $request->string('fdi_primaire')->toString();

        $cacheKey = sprintf('fdi_rech_comp.index.%s.%s.%s', $page, $perPage, md5($fdi));

        $data = CacheTagger::tags(['fdi_rech_comp'])->remember($cacheKey, now()->addMinutes(5), function () use ($fdi, $perPage) {
            $paginator = FdiRechComp::query()
                ->when($fdi, fn ($query) => $query->where('fdi_primaire', $fdi))
                ->latest('created_at')
                ->paginate($perPage);

            return $this->formatPaginator($paginator);
        });

        return response()->json([
            'status' => 200,
            'message' => 'Comparaisons FDI récupérées avec succès',
            'data' => $data['data'],
            'links' => $data['links'],
            'meta' => $data['meta']
        ]);
    }

    public function store(StoreFdiRechCompRequest $request): JsonResponse
    {
        $rechComp = FdiRechComp::create($request->validated());

        CacheTagger::tags(['fdi_rech_comp'])->flush();

        return response()->json([
            'status' => 201,
            'message' => 'Comparaison FDI créée avec succès',
            'data' => $rechComp
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

