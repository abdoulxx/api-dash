<?php

namespace Tests\Feature\Api;

use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RechercheControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_recherche_manifestes_filters_results(): void
    {
        ManifesteSg::factory()->create(['num_manifeste' => 'MAN-123']);
        ManifesteSg::factory()->create(['num_manifeste' => 'OTHER']);

        $this->getJson('/api/recherche/manifestes?num_manifeste=MAN-123')
            ->assertOk()
            ->assertJsonFragment(['num_manifeste' => 'MAN-123']);
    }

    public function test_recherche_fdi_filters_by_banque(): void
    {
        FdiSg::factory()->create(['banque' => 'Bank A']);
        FdiSg::factory()->create(['banque' => 'Bank B']);

        $this->getJson('/api/recherche/fdi?banque=Bank A')
            ->assertOk()
            ->assertJsonFragment(['banque' => 'Bank A']);
    }

    public function test_recherche_declaration_filters_by_bureau(): void
    {
        DeclarationSg::factory()->create(['bureau' => 'DKR']);
        DeclarationSg::factory()->create(['bureau' => 'ABJ']);

        $this->getJson('/api/recherche/declarations?bureau=DKR')
            ->assertOk()
            ->assertJsonFragment(['bureau' => 'DKR']);
    }

    public function test_recherche_banque_filters_by_num_dom(): void
    {
        BanqueSad::factory()->create(['num_dom' => 'DOM-123']);
        BanqueSad::factory()->create(['num_dom' => 'DOM-456']);

        $this->getJson('/api/recherche/banque?num_dom=DOM-123')
            ->assertOk()
            ->assertJsonFragment(['num_dom' => 'DOM-123']);
    }
}




