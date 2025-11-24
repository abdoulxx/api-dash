<?php

namespace App\Jobs;

use App\Models\FdiSg;
use App\Services\FdiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\CacheTagger;

class ProcessFdiValidation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $fdiUlid)
    {
        // Use default queue connection (database if Redis not available)
        $this->onConnection(config('queue.default', 'database'));
    }

    public function handle(FdiService $service): void
    {
        $fdi = Str::isUlid($this->fdiUlid)
            ? FdiSg::where('ulid', $this->fdiUlid)->first()
            : FdiSg::find($this->fdiUlid);

        if (! $fdi) {
            Log::channel('fdi')->warning('ProcessFdiValidation: FDI introuvable', ['fdi_ulid' => $this->fdiUlid]);
            return;
        }

        $result = $service->validateFdi($fdi);

        CacheTagger::tags(['fdi_sg', 'fdi_validation'])->put(
            "fdi_validation_{$fdi->ulid}",
            $result,
            now()->addDay()
        );

        Log::channel('fdi')->info('Résultat de validation stocké', [
            'fdi_ulid' => $fdi->ulid,
            'numero_fdi' => $fdi->numero_fdi,
            'result' => $result,
        ]);
    }
}

