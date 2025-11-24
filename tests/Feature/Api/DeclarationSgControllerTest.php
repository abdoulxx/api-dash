<?php

namespace Tests\Feature\Api;

use App\Models\DeclarationArticle;
use App\Models\DeclarationSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeclarationSgControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_declarations(): void
    {
        DeclarationSg::factory()->count(3)->create();

        $this->getJson('/api/declarations/sg')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_creates_a_declaration(): void
    {
        $payload = DeclarationSg::factory()->make()->toArray();

        $this->postJson('/api/declarations/sg', $payload)
            ->assertCreated()
            ->assertJsonFragment(['declaration' => $payload['declaration']]);

        $this->assertDatabaseHas('declaration_sg', ['declaration' => $payload['declaration']]);
    }

    public function test_it_updates_a_declaration(): void
    {
        $declaration = DeclarationSg::factory()->create();

        $this->putJson("/api/declarations/sg/{$declaration->id}", ['bureau' => 'TEST'])
            ->assertOk()
            ->assertJsonFragment(['bureau' => 'TEST']);

        $this->assertDatabaseHas('declaration_sg', ['id' => $declaration->id, 'bureau' => 'TEST']);
    }

    public function test_it_deletes_a_declaration(): void
    {
        $declaration = DeclarationSg::factory()->create();

        $this->deleteJson("/api/declarations/sg/{$declaration->id}")
            ->assertOk();

        $this->assertSoftDeleted('declaration_sg', ['id' => $declaration->id]);
    }

    public function test_it_calculates_taxes(): void
    {
        $declaration = DeclarationSg::factory()->create(['declaration' => 'DEC-999']);

        DeclarationArticle::factory()->create([
            'declaration' => 'DEC-999',
            'valcaf' => 100,
            'valfob' => 50,
            'droits_taxes' => 25,
        ]);

        $this->postJson("/api/declarations/sg/{$declaration->id}/calculate-taxes")
            ->assertOk()
            ->assertJsonFragment(['declaration' => 'DEC-999'])
            ->assertJsonPath('totals.caf', 100)
            ->assertJsonPath('totals.taxes', 25);
    }
}


