<?php

namespace Database\Factories;

use App\Models\DeclarationTc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeclarationTc>
 */
class DeclarationTcFactory extends Factory
{
    protected $model = DeclarationTc::class;

    public function definition(): array
    {
        return [
            'instanceid' => $this->faker->unique()->numberBetween(1, 100000),
            'annee' => (string) $this->faker->numberBetween(2020, 2025),
            'num_manifeste' => $this->faker->numerify('MAN-#####'),
            'num_bl' => $this->faker->numerify('BL-#####'),
            'numenr' => $this->faker->numerify('ENR-#####'),
            'numero_conteneur' => strtoupper($this->faker->bothify('????#######')),
            'taille_conteneur' => $this->faker->randomElement(['20', '40']),
        ];
    }
}




