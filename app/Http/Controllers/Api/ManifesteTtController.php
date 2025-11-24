<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManifesteTt;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ManifesteTtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $search = $request->string('search')->toString();

        $cacheKey = sprintf('manifeste_tt.index.%s.%s', $perPage, md5($request->fullUrl()));

        $payload = CacheTagger::tags(['manifeste_tt'])->remember($cacheKey, 300, function () use ($perPage, $search) {
            $paginator = ManifesteTt::query()
                ->when($search, function ($query) use ($search) {
                    $query->where('num_manifeste', 'like', "%{$search}%")
                        ->orWhere('num_titre_transport', 'like', "%{$search}%")
                        ->orWhere('code_importateur', 'like', "%{$search}%");
                })
                ->latest('date_manifeste')
                ->paginate($perPage);

            return $this->formatPaginator($paginator);
        });

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $manifeste = ManifesteTt::create($data);

        CacheTagger::tags(['manifeste_tt'])->flush();

        return response()->json([
            'data' => $manifeste,
        ], 201);
    }

    public function show(string $tt): JsonResponse
    {
        $cacheKey = "manifeste_tt.show.{$tt}";

        $payload = CacheTagger::tags(['manifeste_tt'])->remember($cacheKey, 300, function () use ($tt) {
            $manifeste = $this->findByInstanceId($tt);

            return ['data' => $manifeste];
        });

        return response()->json($payload);
    }

    public function update(Request $request, string $tt): JsonResponse
    {
        $manifeste = $this->findByInstanceId($tt);

        $data = $request->validate($this->rules(isUpdate: true));

        $manifeste->fill($data)->save();

        CacheTagger::tags(['manifeste_tt'])->flush();

        return response()->json([
            'data' => $manifeste->fresh(),
        ]);
    }

    public function destroy(string $tt): JsonResponse
    {
        $manifeste = $this->findByInstanceId($tt);
        $manifeste->delete();

        CacheTagger::tags(['manifeste_tt'])->flush();

        return response()->json(null, 204);
    }

    private function findByInstanceId(string $instanceId): ManifesteTt
    {
        return ManifesteTt::where('instance_id', $instanceId)->firstOrFail();
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

    private function rules(bool $isUpdate = false): array
    {
        $stringNullable = 'nullable|string';
        $numericNullable = 'nullable|numeric';
        $dateNullable = 'nullable|date';

        $rules = [
            'etat_apurement' => $stringNullable,
            'code_bureau' => $stringNullable,
            'code_bureau_de_provenance' => $stringNullable,
            'nom_bureau' => $stringNullable,
            'num_voy_ds' => $stringNullable,
            'num_voy_de_provenance' => $stringNullable,
            'date_arrive' => $dateNullable,
            'num_titre_transport' => $stringNullable,
            'num_bl_provenance' => $stringNullable,
            'annee_manifeste' => 'nullable|integer',
            'num_man_sydam' => 'nullable|integer',
            'num_manifeste' => $stringNullable,
            'date_manifeste' => $dateNullable,
            'code_consignataire' => $stringNullable,
            'nom_consignataire' => $stringNullable,
            'adresse_consignataire' => $stringNullable,
            'ligne_manfeste' => 'nullable|integer',
            'status_cns' => $stringNullable,
            'nature_cns' => $stringNullable,
            'nombre_conteneur' => 'nullable|integer',
            'poids_brut' => $numericNullable,
            'poids_restant' => $numericNullable,
            'type_cns' => $stringNullable,
            'libelle_cns' => $stringNullable,
            'code_exportateur' => $stringNullable,
            'nom_exportateur' => $stringNullable,
            'code_importateur' => $stringNullable,
            'nom_importateur' => $stringNullable,
            'nom_navire' => $stringNullable,
            'code_mode_transport' => $stringNullable,
            'nom_mode_transport' => $stringNullable,
            'nationalite_navire' => $stringNullable,
            'code_nationalite_navire' => $stringNullable,
            'notifie_a' => $stringNullable,
            'adresse_notifie_a' => $stringNullable,
            'lieu_charg' => $stringNullable,
            'nom_lieu_charg' => $stringNullable,
            'lieu_decharg' => $stringNullable,
            'nom_lieu_decharg' => $stringNullable,
            'nature_marchandise' => $stringNullable,
            'description_marchandise' => $stringNullable,
            'code_emballage' => $stringNullable,
            'nature_emballage' => $stringNullable,
            'nbr_colis' => $numericNullable,
        ];

        if (! $isUpdate) {
            $rules['instance_id'] = ['required', 'integer', 'unique:manifeste_tt,instance_id'];
        }

        return $rules;
    }
}

