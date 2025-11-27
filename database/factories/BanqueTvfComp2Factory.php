<?php

namespace Database\Factories;

use App\Models\BanqueTvfComp2;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BanqueTvfComp2>
 */
class BanqueTvfComp2Factory extends Factory
{
    protected $model = BanqueTvfComp2::class;

    public function definition(): array
    {
        return [
            'old_id' => (string) $this->faker->unique()->numberBetween(1, 100000),
            'num_fdi' => $this->faker->numerify('FDI-#####'),
            'date_fdi' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'status_fdi' => $this->faker->randomElement(['APPROVED', 'PENDING']),
        ];
    }
}





