<?php

namespace Database\Factories;

use App\Models\DeclarationSg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeclarationSg>
 */
class DeclarationSgFactory extends Factory
{
    protected $model = DeclarationSg::class;

    public function definition(): array
    {
        return [
            'instanceid' => $this->faker->unique()->numberBetween(1, 100000),
            'declaration' => $this->faker->unique()->numerify('DEC-#####'),
            'num_manifeste' => $this->faker->numerify('MAN-#####'),
            'num_fdi' => $this->faker->numerify('FDI-#####'),
            'date_declaration' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'bureau' => $this->faker->randomElement(['ABJ', 'BKO', 'DKR']),
            'importateur' => $this->faker->company,
            'exportateur' => $this->faker->company,
            'valeur_caf_declaration' => $this->faker->randomFloat(2, 1000, 100000),
        ];
    }
}




