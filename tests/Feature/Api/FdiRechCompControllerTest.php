<?php

namespace Tests\Feature\Api;

use App\Models\FdiRechComp;
use App\Models\FdiSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FdiRechCompControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_creates_a_recherche_comparaison(): void
    {
        $primary = FdiSg::factory()->create();
        $secondary = FdiSg::factory()->create();

        $payload = FdiRechComp::factory()->make([
            'fdi_primaire' => $primary->numero_fdi,
            'fdi_secondaire' => $secondary->numero_fdi,
        ])->toArray();

        $this->postJson('/api/fdi/rech-comp', $payload)
            ->assertCreated()
            ->assertJsonFragment(['fdi_primaire' => $primary->numero_fdi]);

        $this->assertDatabaseHas('fdi_rech_comp', [
            'fdi_primaire' => $primary->numero_fdi,
            'fdi_secondaire' => $secondary->numero_fdi,
        ]);
    }
}

