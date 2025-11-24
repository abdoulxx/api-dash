<?php

namespace Tests\Feature\Api;

use App\Models\BanqueSad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BanqueSadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_banque_sad(): void
    {
        BanqueSad::factory()->count(2)->create();

        $this->getJson('/api/banques/sad')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_creates_banque_sad(): void
    {
        $payload = BanqueSad::factory()->make()->toArray();

        $this->postJson('/api/banques/sad', $payload)
            ->assertCreated()
            ->assertJsonFragment(['num_ddu' => $payload['num_ddu']]);

        $this->assertDatabaseHas('banque_sad', ['num_ddu' => $payload['num_ddu']]);
    }

    public function test_it_validates_banque_sad(): void
    {
        $banqueSad = BanqueSad::factory()->create();

        $this->postJson("/api/banques/sad/{$banqueSad->id}/validate")
            ->assertOk()
            ->assertJsonStructure(['valid', 'missing']);
    }
}




