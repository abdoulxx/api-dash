<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessControle;
use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FcvrSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Services\AuditService;
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
        $cacheKey = sprintf(
            'controle:fdi-compare:%s:%s',
            $primary->ulid,
            $secondary->ulid
        );

        $payload = CacheTagger::tags(['controles', 'fdi'])->remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($primary, $secondary) {
                return $this->service->controleFdiCompare($primary, $secondary);
            }
        );

        // Log audit
        AuditService::log(
            'compare',
            sprintf(
                'Comparaison FDI %s (primaire) avec FDI %s (secondaire) effectuée',
                $primary->identifiant,
                $secondary->identifiant
            ),
            'FdiSg',
            $primary->id,
            null,
            [
                'primary_ulid' => $primary->ulid,
                'primary_identifiant' => $primary->identifiant,
                'secondary_ulid' => $secondary->ulid,
                'secondary_identifiant' => $secondary->identifiant,
                'summary' => $payload['data']['summary'] ?? [],
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => $payload['message'],
            'data' => $payload['data'],
        ]);
    }

    public function compareFcvr(FcvrSg $fcvr, DeclarationSg $declaration): JsonResponse
    {
        $cacheKey = sprintf(
            'controle:fcvr-declaration:%s:%s',
            $fcvr->ulid,
            $declaration->ulid
        );

        $payload = CacheTagger::tags(['controles', 'fcvr', 'declarations'])->remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($fcvr, $declaration) {
                return $this->service->controleFcvrDeclaration($fcvr, $declaration);
            }
        );

        // Log audit
        AuditService::log(
            'compare',
            sprintf(
                'Comparaison FCVR %s avec Déclaration %s effectuée',
                $fcvr->identifiant,
                $declaration->identifiant
            ),
            'FcvrSg',
            $fcvr->id,
            null,
            [
                'fcvr_ulid' => $fcvr->ulid,
                'fcvr_identifiant' => $fcvr->identifiant,
                'declaration_ulid' => $declaration->ulid,
                'declaration_identifiant' => $declaration->identifiant,
                'summary' => $payload['data']['summary'] ?? [],
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => $payload['message'],
            'data' => $payload['data'],
        ]);
    }

    public function compareManifeste(ManifesteSg $manifeste, DeclarationSg $declaration): JsonResponse
    {
        $cacheKey = sprintf(
            'controle:manifeste-declaration:%s:%s',
            $manifeste->ulid,
            $declaration->ulid
        );

        $payload = CacheTagger::tags(['controles', 'manifestes', 'declarations'])->remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($manifeste, $declaration) {
                return $this->service->controleManifesteDeclaration($manifeste, $declaration);
            }
        );

        // Log audit
        AuditService::log(
            'compare',
            sprintf(
                'Comparaison Manifeste %s avec Déclaration %s effectuée',
                $manifeste->identifiant,
                $declaration->identifiant
            ),
            'ManifesteSg',
            $manifeste->instance_id,
            null,
            [
                'manifeste_ulid' => $manifeste->ulid,
                'manifeste_identifiant' => $manifeste->identifiant,
                'declaration_ulid' => $declaration->ulid,
                'declaration_identifiant' => $declaration->identifiant,
                'summary' => $payload['data']['summary'] ?? [],
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => $payload['message'],
            'data' => $payload['data'],
        ]);
    }

    public function compareBanque(BanqueSad $banqueSad, DeclarationSg $declaration): JsonResponse
    {
        $cacheKey = sprintf(
            'controle:banque-declaration:%s:%s',
            $banqueSad->ulid,
            $declaration->ulid
        );

        $payload = CacheTagger::tags(['controles', 'banque-sad', 'declarations'])->remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($banqueSad, $declaration) {
                return $this->service->controleBanqueAc($banqueSad, $declaration);
            }
        );

        // Construire un identifiant pour BanqueSad
        $banqueIdentifiant = $banqueSad->num_ddu 
            ? "DDU {$banqueSad->num_ddu}" 
            : ($banqueSad->num_dom ? "DOM {$banqueSad->num_dom}" : "SAD #{$banqueSad->id}");

        // Log audit
        AuditService::log(
            'compare',
            sprintf(
                'Comparaison Banque SAD %s avec Déclaration %s effectuée',
                $banqueIdentifiant,
                $declaration->identifiant
            ),
            'BanqueSad',
            $banqueSad->id,
            null,
            [
                'banque_ulid' => $banqueSad->ulid,
                'banque_identifiant' => $banqueIdentifiant,
                'declaration_ulid' => $declaration->ulid,
                'declaration_identifiant' => $declaration->identifiant,
                'summary' => $payload['data']['summary'] ?? [],
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => $payload['message'],
            'data' => $payload['data'],
        ]);
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

        // Dispatch le job dans la queue
        Bus::dispatch(new ProcessControle(
            $data['type'],
            $data['payload'],
            $cacheKey
        ));

        // Construire l'endpoint de résultat
        $resultUrl = url("/api/controle/result?reference={$cacheKey}");

        // Construire un message descriptif basé sur le type
        $typeLabels = [
            'fdi_compare' => 'Comparaison FDI',
            'fcvr_declaration' => 'Comparaison FCVR/Déclaration',
            'manifeste_declaration' => 'Comparaison Manifeste/Déclaration',
            'banque_declaration' => 'Comparaison Banque SAD/Déclaration',
        ];

        $typeLabel = $typeLabels[$data['type']] ?? $data['type'];

        // Log audit
        AuditService::log(
            'dispatch',
            sprintf(
                'Contrôle %s dispatché en queue (référence: %s)',
                $typeLabel,
                $cacheKey
            ),
            'ProcessControle',
            null,
            null,
            [
                'type' => $data['type'],
                'payload' => $data['payload'],
                'cache_key' => $cacheKey,
                'result_url' => $resultUrl,
            ]
        );

        return response()->json([
            'status' => 202,
            'message' => sprintf(
                'Contrôle %s dispatché avec succès. Le traitement est en cours.',
                $typeLabel
            ),
            'data' => [
                'queued' => true,
                'reference' => $cacheKey,
                'type' => $data['type'],
                'result_endpoint' => $resultUrl,
                'status_endpoint' => $resultUrl, // Alias pour compatibilité
            ],
            'links' => [
                'result' => $resultUrl,
                'self' => url('/api/controle/dispatch'),
            ],
        ], 202);
    }

    public function result(Request $request): JsonResponse
    {
        $key = $request->query('reference');

        if (!$key) {
            return response()->json([
                'status' => 400,
                'message' => 'Le paramètre "reference" est requis',
                'data' => null,
            ], 400);
        }

        // Récupérer le résultat depuis le cache Redis
        $result = CacheTagger::tags(['controles'])->get($key);

        if ($result === null) {
            // Le résultat n'est pas encore disponible ou a expiré
            return response()->json([
                'status' => 202,
                'message' => 'Le traitement est en cours ou le résultat a expiré. Veuillez réessayer dans quelques instants.',
                'data' => [
                    'reference' => $key,
                    'status' => 'processing',
                    'result' => null,
                ],
                'links' => [
                    'self' => url("/api/controle/result?reference={$key}"),
                ],
            ], 202);
        }

        // Le résultat est disponible
        $status = isset($result['message']) && isset($result['data']) ? 200 : 200;
        $message = $result['message'] ?? 'Résultat du contrôle récupéré avec succès';

        // Log audit pour la récupération du résultat
        AuditService::log(
            'retrieve',
            sprintf(
                'Résultat du contrôle récupéré (référence: %s)',
                $key
            ),
            'ProcessControle',
            null,
            null,
            [
                'reference' => $key,
                'has_result' => true,
                'result_type' => $result['data']['type'] ?? null,
            ]
        );

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => [
                'reference' => $key,
                'status' => 'completed',
                'result' => $result,
            ],
            'links' => [
                'self' => url("/api/controle/result?reference={$key}"),
            ],
        ], $status);
    }
}



