<?php

namespace App\Jobs;

use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FcvrSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Services\AuditService;
use App\Services\ControleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Support\CacheTagger;

class ProcessControle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $type,
        public readonly array $payload,
        public readonly string $cacheKey
    ) {
        $this->onQueue('controles');
    }

    public function handle(ControleService $service): void
    {
        try {
            $result = match ($this->type) {
                'fdi_compare' => $service->controleFdiCompare(
                    $this->resolveFdi($this->payload['primary_ulid'] ?? $this->payload['primary_id']),
                    $this->resolveFdi($this->payload['secondary_ulid'] ?? $this->payload['secondary_id'])
                ),
                'fcvr_declaration' => $service->controleFcvrDeclaration(
                    $this->resolveFcvr($this->payload['fcvr_ulid'] ?? $this->payload['fcvr_id']),
                    $this->resolveDeclaration($this->payload['declaration_ulid'] ?? $this->payload['declaration_id'])
                ),
                'manifeste_declaration' => $service->controleManifesteDeclaration(
                    $this->resolveManifeste($this->payload['manifeste_ulid'] ?? $this->payload['manifeste_id']),
                    $this->resolveDeclaration($this->payload['declaration_ulid'] ?? $this->payload['declaration_id'])
                ),
                'banque_declaration' => $service->controleBanqueAc(
                    $this->resolveBanqueSad($this->payload['banque_ulid'] ?? $this->payload['banque_id']),
                    $this->resolveDeclaration($this->payload['declaration_ulid'] ?? $this->payload['declaration_id'])
                ),
                default => throw new \InvalidArgumentException("Type de contrôle inconnu: {$this->type}"),
            };

            // Stocker le résultat dans le cache Redis avec tags pour invalidation ciblée
            CacheTagger::tags(['controles', $this->type])
                ->put($this->cacheKey, $result, now()->addHour());

            // Log audit pour le traitement réussi
            $typeLabels = [
                'fdi_compare' => 'Comparaison FDI',
                'fcvr_declaration' => 'Comparaison FCVR/Déclaration',
                'manifeste_declaration' => 'Comparaison Manifeste/Déclaration',
                'banque_declaration' => 'Comparaison Banque SAD/Déclaration',
            ];

            AuditService::log(
                'process',
                sprintf(
                    'Contrôle %s traité avec succès (référence: %s)',
                    $typeLabels[$this->type] ?? $this->type,
                    $this->cacheKey
                ),
                'ProcessControle',
                null,
                null,
                [
                    'type' => $this->type,
                    'cache_key' => $this->cacheKey,
                    'has_result' => true,
                    'result_type' => $result['data']['type'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            // En cas d'erreur, stocker l'erreur dans le cache
            $errorResult = [
                'message' => sprintf(
                    'Erreur lors du traitement du contrôle %s: %s',
                    $this->type,
                    $e->getMessage()
                ),
                'error' => true,
                'error_message' => $e->getMessage(),
                'error_type' => get_class($e),
            ];

            CacheTagger::tags(['controles', $this->type])
                ->put($this->cacheKey, $errorResult, now()->addMinutes(30));

            // Log audit pour l'erreur
            AuditService::log(
                'error',
                sprintf(
                    'Erreur lors du traitement du contrôle %s (référence: %s): %s',
                    $this->type,
                    $this->cacheKey,
                    $e->getMessage()
                ),
                'ProcessControle',
                null,
                null,
                [
                    'type' => $this->type,
                    'cache_key' => $this->cacheKey,
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                ]
            );

            // Re-throw pour que Laravel puisse gérer la retry si nécessaire
            throw $e;
        }
    }

    private function resolveFdi(string|int $identifier): FdiSg
    {
        if (is_string($identifier) && Str::isUlid($identifier)) {
            return FdiSg::where('ulid', $identifier)->firstOrFail();
        }

        return FdiSg::findOrFail($identifier);
    }

    private function resolveFcvr(string|int $identifier): FcvrSg
    {
        if (is_string($identifier) && Str::isUlid($identifier)) {
            return FcvrSg::where('ulid', $identifier)->firstOrFail();
        }

        return FcvrSg::findOrFail($identifier);
    }

    private function resolveDeclaration(string|int $identifier): DeclarationSg
    {
        if (is_string($identifier) && Str::isUlid($identifier)) {
            return DeclarationSg::where('ulid', $identifier)->firstOrFail();
        }

        return DeclarationSg::findOrFail($identifier);
    }

    private function resolveManifeste(string|int $identifier): ManifesteSg
    {
        if (is_string($identifier) && Str::isUlid($identifier)) {
            return ManifesteSg::where('ulid', $identifier)->firstOrFail();
        }

        return ManifesteSg::findOrFail($identifier);
    }

    private function resolveBanqueSad(string|int $identifier): BanqueSad
    {
        if (is_string($identifier) && Str::isUlid($identifier)) {
            return BanqueSad::where('ulid', $identifier)->firstOrFail();
        }

        return BanqueSad::findOrFail($identifier);
    }
}



