<?php

namespace Tests\Feature\Api;

use App\Models\DeclarationSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_dashboard_alerts_endpoint(): void
    {
        ManifesteSg::factory()->create(['date_arrivee_navire' => now()->subDays(10)]);
        FdiSg::factory()->create(['derniere_operation' => null]);
        DeclarationSg::factory()->create(['date_quittance' => null]);

        $this->getJson('/api/dashboard/alerts')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['manifeste_en_retard', 'fdi_sans_validation', 'declarations_sans_quittance']]);
    }

    public function test_dashboard_delais_endpoint(): void
    {
        DeclarationSg::factory()->create([
            'date_declaration' => now()->subDays(5),
            'date_quittance' => now()->subDays(2),
        ]);

        $this->getJson('/api/dashboard/delais')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['delai_moyen_fdi', 'delai_moyen_declaration']]);
    }
}




