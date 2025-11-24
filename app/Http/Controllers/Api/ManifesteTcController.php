<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManifesteTc;
use App\Support\CacheTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ManifesteTcController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $search = $request->string('search')->toString();

        $cacheKey = sprintf('manifeste_tc.index.%s.%s', $perPage, md5($request->fullUrl()));

        $payload = CacheTagger::tags(['manifeste_tc'])->remember($cacheKey, 300, function () use ($perPage, $search) {
            $paginator = ManifesteTc::query()
                ->when($search, function ($query) use ($search) {
                    $query->where('num_manifeste', 'like', "%{$search}%")
                        ->orWhere('numero_bl', 'like', "%{$search}%")
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

        $manifeste = ManifesteTc::create($data);

        CacheTagger::tags(['manifeste_tc'])->flush();

        return response()->json([
            'data' => $manifeste,
        ], 201);
    }

    public function show(string $tc): JsonResponse
    {
        $cacheKey = "manifeste_tc.show.{$tc}";

        $payload = CacheTagger::tags(['manifeste_tc'])->remember($cacheKey, 300, function () use ($tc) {
            $manifeste = $this->findByInstanceId($tc);

            return ['data' => $manifeste];
        });

        return response()->json($payload);
    }

    public function update(Request $request, string $tc): JsonResponse
    {
        $manifeste = $this->findByInstanceId($tc);

        $data = $request->validate($this->rules(isUpdate: true));

        $manifeste->fill($data)->save();

        CacheTagger::tags(['manifeste_tc'])->flush();

        return response()->json([
            'data' => $manifeste->fresh(),
        ]);
    }

    public function destroy(string $tc): JsonResponse
    {
        $manifeste = $this->findByInstanceId($tc);
        $manifeste->delete();

        CacheTagger::tags(['manifeste_tc'])->flush();

        return response()->json(null, 204);
    }

    private function findByInstanceId(string $instanceId): ManifesteTc
    {
        return ManifesteTc::where('instance_id', $instanceId)->firstOrFail();
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
            'numero_voyage' => $stringNullable,
            'numero_voyage_de_provenance' => $stringNullable,
            'date_arrive' => $dateNullable,
            'numero_bl' => $stringNullable,
            'numero_bl_provenance' => $stringNullable,
            'annee_manifeste' => 'nullable|integer',
            'num_man_sydam' => 'nullable|integer',
            'num_manifeste' => $stringNullable,
            'date_manifeste' => $dateNullable,
            'code_consignataire' => $stringNullable,
            'nom_consignataire' => $stringNullable,
            'adresse_manifeste' => $stringNullable,
            'ligne_manfeste' => 'nullable|integer',
            'status_manifeste' => $stringNullable,
            'nature_manifeste' => $stringNullable,
            'nombre_conteneur' => 'nullable|integer',
            'poids_brut' => $numericNullable,
            'poids_restant' => $numericNullable,
            'type_manifeste' => $stringNullable,
            'libelle_manifeste' => $stringNullable,
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
            'code_port_chargement' => $stringNullable,
            'nom_port_chargement' => $stringNullable,
            'code_port_dechargement' => $stringNullable,
            'nom_port_dechargement' => $stringNullable,
            'code_emballage' => $stringNullable,
            'nature_emballage' => $stringNullable,
            'nombre_colis' => $numericNullable,
            'nbre_colis_conteneur' => 'nullable|integer',
            'num_conteneur' => $stringNullable,
            'taille_conteneur' => $stringNullable,
            'plomb1' => $stringNullable,
            'plomb2' => $stringNullable,
        ];

        if (! $isUpdate) {
            $rules['instance_id'] = ['required', 'integer', 'unique:manifeste_tc,instance_id'];
        }

        return $rules;
    }
}

