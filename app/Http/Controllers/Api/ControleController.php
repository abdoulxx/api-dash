<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessControle;
use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FcvrSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Services\ControleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Support\CacheTagger;

class ControleController extends Controller
{
    public function __construct(private readonly ControleService $service)
    {
    }

    public function compareFdi(FdiSg $primary, FdiSg $secondary): JsonResponse
    {
        return response()->json($this->service->controleFdiCompare($primary, $secondary));
    }

    public function compareFcvr(FcvrSg $fcvr, DeclarationSg $declaration): JsonResponse
    {
        return response()->json($this->service->controleFcvrDeclaration($fcvr, $declaration));
    }

    public function compareManifeste(ManifesteSg $manifeste, DeclarationSg $declaration): JsonResponse
    {
        return response()->json($this->service->controleManifesteDeclaration($manifeste, $declaration));
    }

    public function compareBanque(BanqueSad $banqueSad, DeclarationSg $declaration): JsonResponse
    {
        return response()->json($this->service->controleBanqueAc($banqueSad, $declaration));
    }

    public function dispatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:fdi_compare,fcvr_declaration,manifeste_declaration,banque_declaration',
            'payload' => 'required|array',
        ]);

        $cacheKey = sprintf(
            'controle:%s:%s',
            $data['type'],
            Str::random(12)
        );

        Bus::dispatch(new ProcessControle(
            $data['type'],
            $data['payload'],
            $cacheKey
        ));

        return response()->json([
            'queued' => true,
            'reference' => $cacheKey,
        ], 202);
    }

    public function result(Request $request): JsonResponse
    {
        $key = $request->query('reference');

        $result = CacheTagger::tags(['controles'])->get($key);

        return response()->json([
            'reference' => $key,
            'result' => $result,
        ]);
    }
}



