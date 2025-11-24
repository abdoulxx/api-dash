<?php

namespace App\Jobs;

use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FcvrSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
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
        $result = match ($this->type) {
            'fdi_compare' => $service->controleFdiCompare(
                $this->resolveFdi($this->payload['primary_ulid'] ?? $this->payload['primary_id']),
                $this->resolveFdi($this->payload['secondary_ulid'] ?? $this->payload['secondary_id'])
            ),
            'fcvr_declaration' => $service->controleFcvrDeclaration(
                FcvrSg::findOrFail($this->payload['fcvr_id']),
                DeclarationSg::findOrFail($this->payload['declaration_id'])
            ),
            'manifeste_declaration' => $service->controleManifesteDeclaration(
                ManifesteSg::findOrFail($this->payload['manifeste_id']),
                DeclarationSg::findOrFail($this->payload['declaration_id'])
            ),
            'banque_declaration' => $service->controleBanqueAc(
                BanqueSad::findOrFail($this->payload['banque_id']),
                DeclarationSg::findOrFail($this->payload['declaration_id'])
            ),
            default => throw new \InvalidArgumentException("Type de contrôle inconnu: {$this->type}"),
        };

        CacheTagger::tags(['controles'])
            ->put($this->cacheKey, $result, now()->addHour());
    }

    private function resolveFdi(string|int $identifier): FdiSg
    {
        if (is_string($identifier) && Str::isUlid($identifier)) {
            return FdiSg::where('ulid', $identifier)->firstOrFail();
        }

        return FdiSg::findOrFail($identifier);
    }
}



