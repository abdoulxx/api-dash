<?php

namespace Tests\Feature\Api;

use App\Models\ManifesteSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManifesteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_can_list_manifestes(): void
    {
        ManifesteSg::factory()->count(2)->create();

        $response = $this->getJson('/api/manifestes/sg');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_can_show_single_manifeste(): void
    {
        $manifeste = ManifesteSg::factory()->create();

        $response = $this->getJson("/api/manifestes/sg/{$manifeste->getKey()}");

        $response->assertOk()
            ->assertJsonFragment(['num_manifeste' => $manifeste->num_manifeste]);
    }
}





