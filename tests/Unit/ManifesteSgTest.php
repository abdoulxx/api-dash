<?php

namespace Tests\Unit;

use App\Models\ManifesteSg;
use App\Models\ManifesteTt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManifesteSgTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_manifeste(): void
    {
        $manifeste = ManifesteSg::factory()->create();

        $this->assertDatabaseHas('manifeste_sg', [
            'instance_id' => $manifeste->instance_id,
        ]);
    }

    public function test_manifeste_has_titres_transport(): void
    {
        $manifeste = ManifesteSg::factory()->create();
        ManifesteTt::factory()->create([
            'num_manifeste' => $manifeste->num_manifeste,
        ]);

        $this->assertCount(1, $manifeste->fresh()->titresTransport);
    }
}





