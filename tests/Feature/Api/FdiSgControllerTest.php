<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessFdiValidation;
use App\Models\FdiArticle;
use App\Models\FdiSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FdiSgControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_it_lists_fdi_sg(): void
    {
        FdiSg::factory()->count(3)->create();

        $response = $this->getJson('/api/fdi/sg');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_creates_an_fdi(): void
    {
        $payload = FdiSg::factory()->make()->toArray();

        $response = $this->postJson('/api/fdi/sg', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['numero_fdi' => $payload['numero_fdi']]);

        $this->assertDatabaseHas('fdi_sg', ['numero_fdi' => $payload['numero_fdi']]);
    }

    public function test_it_updates_an_fdi(): void
    {
        $fdi = FdiSg::factory()->create();

        $response = $this->putJson("/api/fdi/sg/{$fdi->ulid}", [
            'banque' => 'BANQUE DE TEST',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['banque' => 'BANQUE DE TEST']);

        $this->assertDatabaseHas('fdi_sg', ['ulid' => $fdi->ulid, 'banque' => 'BANQUE DE TEST']);
    }

    public function test_it_deletes_an_fdi(): void
    {
        $fdi = FdiSg::factory()->create();

        $this->deleteJson("/api/fdi/sg/{$fdi->ulid}")
            ->assertOk();

        $this->assertDatabaseMissing('fdi_sg', ['ulid' => $fdi->ulid]);
    }

    public function test_it_returns_related_articles(): void
    {
        $fdi = FdiSg::factory()->create();
        FdiArticle::factory()->count(2)->create(['numero_fdi' => $fdi->numero_fdi]);

        $this->getJson("/api/fdi/sg/{$fdi->ulid}/articles")
            ->assertOk()
            ->assertJsonFragment(['numero_fdi' => $fdi->numero_fdi]);
    }

    public function test_it_compares_two_fdi(): void
    {
        $primary = FdiSg::factory()->create(['valeur_caf' => 1000]);
        $secondary = FdiSg::factory()->create(['valeur_caf' => 2000]);

        $this->postJson("/api/fdi/sg/{$primary->ulid}/compare", ['secondary_ulid' => $secondary->ulid])
            ->assertOk()
            ->assertJsonPath('data.primary.numero_fdi', $primary->numero_fdi)
            ->assertJsonPath('data.secondary.numero_fdi', $secondary->numero_fdi);
    }

    public function test_it_dispatches_validation_job(): void
    {
        Bus::fake();

        $fdi = FdiSg::factory()->create();

        $this->postJson("/api/fdi/sg/{$fdi->ulid}/validate")
            ->assertOk()
            ->assertJsonFragment(['queued' => true]);

        Bus::assertDispatched(ProcessFdiValidation::class, fn ($job) => $job->fdiUlid === $fdi->ulid);
    }

    public function test_it_calculates_duties(): void
    {
        $fdi = FdiSg::factory()->create(['valeur_caf' => 500, 'valeur_fret_cfa' => 50, 'valeur_assurance_cfa' => 25]);

        $this->postJson("/api/fdi/sg/{$fdi->ulid}/calculate-droits")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['numero_fdi', 'taxable_amount', 'estimated_duties']
            ]);
    }
}

