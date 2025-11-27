<?php

namespace Tests\Feature\Api;

use App\Models\FdiArticle;
use App\Models\FdiSg;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FdiArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_articles(): void
    {
        FdiArticle::factory()->count(2)->create();

        $this->getJson('/api/fdi/articles')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_it_creates_an_article(): void
    {
        $fdi = FdiSg::factory()->create();

        $payload = FdiArticle::factory()->make([
            'numero_fdi' => $fdi->numero_fdi,
        ])->toArray();

        $this->postJson('/api/fdi/articles', $payload)
            ->assertCreated()
            ->assertJsonFragment(['numero_fdi' => $fdi->numero_fdi]);

        $this->assertDatabaseHas('fdi_article', [
            'numero_fdi' => $fdi->numero_fdi,
            'numart' => $payload['numart'],
        ]);
    }
}




