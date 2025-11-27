<?php

namespace Tests\Feature\Api;

use App\Models\BonProvisoireArticle;
use App\Models\BonProvisoireSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BonProvisoireSgControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_bon_provisoire(): void
    {
        BonProvisoireSg::factory()->count(2)->create();

        $this->getJson('/api/bon-provisoires/sg')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_creates_bon_provisoire(): void
    {
        $payload = BonProvisoireSg::factory()->make()->toArray();

        $this->postJson('/api/bon-provisoires/sg', $payload)
            ->assertCreated()
            ->assertJsonFragment(['numero_bon_provisoire' => $payload['numero_bon_provisoire']]);

        $this->assertDatabaseHas('bon_provisoire_sg', ['numero_bon_provisoire' => $payload['numero_bon_provisoire']]);
    }

    public function test_it_updates_bon_provisoire(): void
    {
        $bon = BonProvisoireSg::factory()->create();

        $this->putJson("/api/bon-provisoires/sg/{$bon->id}", ['type_bon_provisoire' => 'RENOUVELE'])
            ->assertOk()
            ->assertJsonFragment(['type_bon_provisoire' => 'RENOUVELE']);

        $this->assertDatabaseHas('bon_provisoire_sg', ['id' => $bon->id, 'type_bon_provisoire' => 'RENOUVELE']);
    }

    public function test_it_returns_articles(): void
    {
        $bon = BonProvisoireSg::factory()->create();

        BonProvisoireArticle::factory()->create([
            'instance_id' => $bon->instance_id,
            'numero_bon_provisoire' => $bon->numero_bon_provisoire,
        ]);

        $this->getJson("/api/bon-provisoires/sg/{$bon->id}/articles")
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}





