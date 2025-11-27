<?php

namespace Tests\Feature\Api;

use App\Models\BanqueTvf;
use App\Models\BanqueTvfComp1;
use App\Models\BanqueTvfComp2;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BanqueTvfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_banque_tvf(): void
    {
        BanqueTvf::factory()->count(2)->create();

        $this->getJson('/api/banques/tvf')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_returns_comparaisons(): void
    {
        $tvf = BanqueTvf::factory()->create(['old_id' => '12345', 'num_fdi' => 'FDI-100']);

        BanqueTvfComp1::factory()->create(['old_id' => '12345']);
        BanqueTvfComp2::factory()->create(['old_id' => '12345']);

        $this->getJson("/api/banques/tvf/{$tvf->id}/comparaisons")
            ->assertOk()
            ->assertJsonStructure(['comparaison_1', 'comparaison_2']);
    }

    public function test_it_validates_domiciliation(): void
    {
        $tvf = BanqueTvf::factory()->create(['num_dom' => 'DOM-100', 'date_dom' => now()]);

        $this->postJson("/api/banques/tvf/{$tvf->id}/validate")
            ->assertOk()
            ->assertJson(['domiciliation_ok' => true]);
    }
}





