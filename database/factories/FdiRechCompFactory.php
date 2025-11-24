<?php

namespace Database\Factories;

use App\Models\FdiRechComp;
use App\Models\FdiSg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FdiRechComp>
 */
class FdiRechCompFactory extends Factory
{
    protected $model = FdiRechComp::class;

    public function definition(): array
    {
        $primary = FdiSg::factory()->create();
        $secondary = FdiSg::factory()->create();

        return [
            'id_fdi_comp' => $this->faker->randomFloat(4, 1000, 9999),
            'fdi_primaire' => $primary->numero_fdi,
            'date_fdi_primaire' => $primary->date_fdi,
            'fdi_secondaire' => $secondary->numero_fdi,
            'date_fdi_secondaire' => $secondary->date_fdi,
            'observation' => $this->faker->sentence(),
        ];
    }
}



