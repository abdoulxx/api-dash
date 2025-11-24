<?php

namespace App\Jobs;

use App\Models\DeclarationArticle;
use App\Models\DeclarationSg;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateDeclarationTaxes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $declarationUlid,
        public readonly string $cacheKey
    ) {
        $this->onQueue('declarations');
    }

    public function handle(): void
    {
        $declaration = DeclarationSg::where('ulid', $this->declarationUlid)->firstOrFail();
        
        $totals = DeclarationArticle::query()
            ->where('declaration', $declaration->declaration)
            ->selectRaw('SUM(valcaf) as caf, SUM(valfob) as fob, SUM(droits_taxes) as taxes')
            ->first();

        $articlesCount = DeclarationArticle::where('declaration', $declaration->declaration)->count();

        $result = [
            'declaration' => $declaration->declaration,
            'declaration_ulid' => $declaration->ulid,
            'articles_count' => $articlesCount,
            'totals' => [
                'caf' => (float) ($totals->caf ?? 0),
                'fob' => (float) ($totals->fob ?? 0),
                'taxes' => (float) ($totals->taxes ?? 0),
            ],
            'totals_formatted' => [
                'caf' => number_format((float) ($totals->caf ?? 0), 2, ',', ' ') . ' XOF',
                'fob' => number_format((float) ($totals->fob ?? 0), 2, ',', ' ') . ' XOF',
                'taxes' => number_format((float) ($totals->taxes ?? 0), 2, ',', ' ') . ' XOF',
            ],
            'calculated_at' => now()->toDateTimeString(),
        ];

        // Store result in cache
        CacheTagger::tags(['declarations'])
            ->put($this->cacheKey, $result, now()->addHour());

        // Log the calculation
        AuditService::log('calculate-taxes', "Calcul des taxes effectué pour la déclaration \"{$declaration->declaration}\" ({$articlesCount} article(s))", 'DeclarationSg', $declaration->id, null, $result);
    }
}

