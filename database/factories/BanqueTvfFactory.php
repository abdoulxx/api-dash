<?php

namespace Database\Factories;

use App\Models\BanqueTvf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BanqueTvf>
 */
class BanqueTvfFactory extends Factory
{
    protected $model = BanqueTvf::class;

    public function definition(): array
    {
        return [
            'old_id' => (string) $this->faker->unique()->numberBetween(1, 100000),
            'num_fdi' => $this->faker->numerify('FDI-#####'),
            'date_fdi' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'status_fdi' => $this->faker->randomElement(['APPROVED', 'PENDING']),
            'num_dom' => $this->faker->numerify('DOM-#####'),
            'date_dom' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'bank_dom' => $this->faker->company,
            'mont_fact_dev' => $this->faker->randomFloat(2, 1000, 100000),
        ];
    }
}




