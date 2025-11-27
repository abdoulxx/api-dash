<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessControle;
use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FdiSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use App\Support\CacheTagger;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ControleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_compares_fdi(): void
    {
        $primary = FdiSg::factory()->create(['banque' => 'A', 'montant_domicilie_cfa' => 100]);
        $secondary = FdiSg::factory()->create(['banque' => 'B', 'montant_domicilie_cfa' => 200]);

        $this->getJson("/api/controle/fdi/{$primary->ulid}/{$secondary->ulid}")
            ->assertOk()
            ->assertJsonFragment(['status' => 200])
            ->assertJsonFragment(['type' => 'fdi_compare'])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'type',
                    'primaire' => ['numero_fdi_complet'],
                    'secondaire' => ['numero_fdi_complet'],
                    'diffs',
                    'context_diffs',
                    'summary' => [
                        'total_financial_diffs',
                        'total_context_diffs',
                        'has_diffs',
                    ],
                ],
            ]);
    }

    public function test_it_dispatches_process_controle_job(): void
    {
        Bus::fake();

        $primary = FdiSg::factory()->create();
        $secondary = FdiSg::factory()->create();

        $response = $this->postJson('/api/controle/dispatch', [
            'type' => 'fdi_compare',
            'payload' => [
                'primary_ulid' => $primary->ulid,
                'secondary_ulid' => $secondary->ulid,
            ],
        ]);

        $response->assertAccepted()
            ->assertJsonStructure(['queued', 'reference']);

        Bus::assertDispatched(ProcessControle::class, function (ProcessControle $job) use ($primary, $secondary) {
            return $job->type === 'fdi_compare'
                && ($job->payload['primary_ulid'] ?? null) === $primary->ulid
                && ($job->payload['secondary_ulid'] ?? null) === $secondary->ulid;
        });
    }

    public function test_it_returns_result_from_cache(): void
    {
        $reference = 'controle:test:abc';
        $payload = [
            'type' => 'banque_declaration',
            'differences' => ['num_ddu' => ['primary' => 'DDU', 'secondary' => 'DEC']],
        ];

        CacheTagger::tags(['controles'])->put($reference, $payload, now()->addMinute());

        $this->getJson("/api/controle/result?reference={$reference}")
            ->assertOk()
            ->assertJsonFragment(['reference' => $reference])
            ->assertJsonFragment(['type' => 'banque_declaration']);
    }

    public function test_process_controle_job_handles_banque_declaration(): void
    {
        $banque = BanqueSad::factory()->create(['num_dom' => 'DOM123', 'date_dom' => now()]);
        $declaration = DeclarationSg::factory()->create(['num_dossier' => 'DOM123']);

        $job = new ProcessControle('banque_declaration', [
            'banque_id' => $banque->id,
            'declaration_id' => $declaration->id,
        ], 'controle:test:key');

        $job->handle(app(\App\Services\ControleService::class));

        $this->assertNotNull(CacheTagger::tags(['controles'])->get('controle:test:key'));
    }
}



