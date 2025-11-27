<?php

namespace Tests\Feature\Performance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_dashboard_endpoint_is_under_threshold(): void
    {
        $durations = [];

        for ($i = 0; $i < 3; $i++) {
            $start = microtime(true);
            $response = $this->getJson('/api/dashboard');
            $response->assertOk();
            $durations[] = (microtime(true) - $start) * 1000;
        }

        sort($durations);
        $p95 = $durations[count($durations) - 1];

        $this->assertLessThanOrEqual(500, $p95, 'Dashboard endpoint exceeded 500ms p95 threshold');
    }
}





